<?php

namespace Tests\Feature\Importers;

use App\Models\Proficiency;
use App\Models\Race;
use App\Models\Size;
use App\Models\Skill;
use App\Models\Source;
use App\Services\Importers\RaceImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RaceImporterTest extends TestCase
{
    use RefreshDatabase;

    private RaceImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = new RaceImporter();
    }

    /** @test */
    public function it_imports_a_race()
    {
        $raceData = [
            'name' => 'Dragonborn',
            'size_code' => 'M',
            'speed' => 30,
            'description' => 'Born of dragons, as their name proclaims.',
            'source_code' => 'PHB',
            'source_pages' => '32',
        ];

        $race = $this->importer->import($raceData);

        $this->assertInstanceOf(Race::class, $race);
        $this->assertEquals('Dragonborn', $race->name);
        $this->assertEquals(30, $race->speed);
        $this->assertStringContainsString('Born of dragons', $race->description);

        // Check relationships
        $this->assertNotNull($race->size);
        $this->assertEquals('M', $race->size->code);
        $this->assertEquals('Medium', $race->size->name);

        $this->assertNotNull($race->source);
        $this->assertEquals('PHB', $race->source->code);
    }

    /** @test */
    public function it_updates_existing_race_on_reimport()
    {
        $raceData = [
            'name' => 'Dragonborn',
            'size_code' => 'M',
            'speed' => 30,
            'description' => 'Original description',
            'source_code' => 'PHB',
            'source_pages' => '32',
        ];

        $this->importer->import($raceData);
        $this->assertEquals(1, Race::count());

        // Re-import with updated description
        $raceData['description'] = 'Updated description';
        $race = $this->importer->import($raceData);

        $this->assertEquals(1, Race::count());
        $this->assertStringContainsString('Updated description', $race->description);
    }

    /** @test */
    public function it_imports_from_xml_file()
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Dragonborn</name>
    <size>M</size>
    <speed>30</speed>
    <ability>Str +2, Cha +1</ability>
    <trait category="description">
      <name>Description</name>
      <text>Born of dragons.

Source: Player's Handbook (2014) p. 32</text>
    </trait>
  </race>
  <race>
    <name>Dwarf, Hill</name>
    <size>M</size>
    <speed>25</speed>
    <ability>Con +2, Wis +1</ability>
    <trait category="description">
      <name>Description</name>
      <text>Bold and hardy.

Source: Player's Handbook (2014) p. 19</text>
    </trait>
  </race>
</compendium>
XML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'race_test_');
        file_put_contents($tmpFile, $xml);

        $count = $this->importer->importFromFile($tmpFile);

        unlink($tmpFile);

        // Should import 3 races: Dragonborn + base Dwarf + Hill subrace
        $this->assertEquals(3, $count);
        $this->assertEquals(3, Race::count());
        $this->assertDatabaseHas('races', ['name' => 'Dragonborn']);
        $this->assertDatabaseHas('races', ['name' => 'Dwarf']);
        $this->assertDatabaseHas('races', ['name' => 'Hill']);
    }

    /** @test */
    public function it_creates_base_race_and_subrace()
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Dwarf, Hill</name>
    <size>M</size>
    <speed>25</speed>
    <trait category="description">
      <name>Description</name>
      <text>As a hill dwarf, you have keen senses.
Source: Player's Handbook (2014) p. 20</text>
    </trait>
  </race>
</compendium>
XML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'race_test_');
        file_put_contents($tmpFile, $xml);

        $count = $this->importer->importFromFile($tmpFile);

        unlink($tmpFile);

        // Should create 2 races: base "Dwarf" + subrace "Hill"
        $this->assertEquals(2, $count);

        // Check base race exists
        $baseRace = Race::where('name', 'Dwarf')
                         ->whereNull('parent_race_id')
                         ->first();
        $this->assertNotNull($baseRace);

        // Check subrace exists and is linked to base
        $subrace = Race::where('name', 'Hill')
                        ->whereNotNull('parent_race_id')
                        ->first();
        $this->assertNotNull($subrace);
        $this->assertEquals($baseRace->id, $subrace->parent_race_id);
    }

    /** @test */
    public function it_creates_only_base_race_when_no_subrace()
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Dragonborn</name>
    <size>M</size>
    <speed>30</speed>
    <trait category="description">
      <name>Description</name>
      <text>Born of dragons.
Source: Player's Handbook (2014) p. 32</text>
    </trait>
  </race>
</compendium>
XML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'race_test_');
        file_put_contents($tmpFile, $xml);

        $count = $this->importer->importFromFile($tmpFile);

        unlink($tmpFile);

        $this->assertEquals(1, $count);

        $race = Race::where('name', 'Dragonborn')->first();
        $this->assertNotNull($race);
        $this->assertNull($race->parent_race_id);
    }

    /** @test */
    public function it_reuses_existing_base_race_for_multiple_subraces()
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Dwarf, Hill</name>
    <size>M</size>
    <speed>25</speed>
    <trait category="description">
      <name>Description</name>
      <text>Hill dwarf.
Source: Player's Handbook (2014) p. 20</text>
    </trait>
  </race>
  <race>
    <name>Dwarf, Mountain</name>
    <size>M</size>
    <speed>25</speed>
    <trait category="description">
      <name>Description</name>
      <text>Mountain dwarf.
Source: Player's Handbook (2014) p. 20</text>
    </trait>
  </race>
</compendium>
XML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'race_test_');
        file_put_contents($tmpFile, $xml);

        $count = $this->importer->importFromFile($tmpFile);

        unlink($tmpFile);

        // Should create 3 races: 1 base "Dwarf" + 2 subraces
        $this->assertEquals(3, $count);

        $baseRaces = Race::where('name', 'Dwarf')
                          ->whereNull('parent_race_id')
                          ->get();
        $this->assertCount(1, $baseRaces, 'Should only create one base Dwarf race');

        $subraces = Race::whereNotNull('parent_race_id')->get();
        $this->assertCount(2, $subraces);
    }

    /** @test */
    public function it_imports_skill_proficiencies()
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Elf, High</name>
    <size>M</size>
    <speed>30</speed>
    <proficiency>Perception</proficiency>
    <trait category="description">
      <name>Description</name>
      <text>High elf.
Source: Player's Handbook (2014) p. 23</text>
    </trait>
  </race>
</compendium>
XML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'race_test_');
        file_put_contents($tmpFile, $xml);

        $this->importer->importFromFile($tmpFile);

        unlink($tmpFile);

        $race = Race::where('name', 'High')->first();
        $this->assertNotNull($race);

        $proficiencies = $race->proficiencies;
        $this->assertCount(1, $proficiencies);

        $proficiency = $proficiencies->first();
        $this->assertEquals('skill', $proficiency->proficiency_type);
        $this->assertNotNull($proficiency->skill_id);
        $this->assertEquals('Perception', $proficiency->skill->name);
    }

    /** @test */
    public function it_imports_weapon_proficiencies_as_text()
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Elf, High</name>
    <size>M</size>
    <speed>30</speed>
    <weapons>Longsword, Shortsword</weapons>
    <trait category="description">
      <name>Description</name>
      <text>High elf.
Source: Player's Handbook (2014) p. 23</text>
    </trait>
  </race>
</compendium>
XML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'race_test_');
        file_put_contents($tmpFile, $xml);

        $this->importer->importFromFile($tmpFile);

        unlink($tmpFile);

        $race = Race::where('name', 'High')->first();
        $proficiencies = $race->proficiencies;

        $this->assertCount(2, $proficiencies);

        $weaponProfs = $proficiencies->where('proficiency_type', 'weapon');
        $this->assertCount(2, $weaponProfs);

        // Should be stored as proficiency_name (items not imported yet)
        $names = $weaponProfs->pluck('proficiency_name')->toArray();
        $this->assertContains('Longsword', $names);
        $this->assertContains('Shortsword', $names);
    }

    /** @test */
    public function it_clears_and_recreates_proficiencies_on_reimport()
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Elf, High</name>
    <size>M</size>
    <speed>30</speed>
    <proficiency>Perception</proficiency>
    <weapons>Longsword</weapons>
    <trait category="description">
      <name>Description</name>
      <text>High elf.
Source: Player's Handbook (2014) p. 23</text>
    </trait>
  </race>
</compendium>
XML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'race_test_');
        file_put_contents($tmpFile, $xml);

        // First import
        $this->importer->importFromFile($tmpFile);
        $race = Race::where('name', 'High')->first();
        $this->assertCount(2, $race->proficiencies);

        // Second import (should clear old proficiencies)
        $this->importer->importFromFile($tmpFile);
        $race->refresh();
        $this->assertCount(2, $race->proficiencies);

        unlink($tmpFile);
    }
}
