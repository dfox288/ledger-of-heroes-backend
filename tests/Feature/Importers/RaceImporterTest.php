<?php

namespace Tests\Feature\Importers;

use App\Models\AbilityScore;
use App\Models\Race;
use App\Services\Importers\RaceImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('importers')]
class RaceImporterTest extends TestCase
{
    use RefreshDatabase;

    private RaceImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = new RaceImporter;
    }

    #[Test]
    public function it_imports_a_race()
    {
        $raceData = [
            'name' => 'Dragonborn',
            'size_code' => 'M',
            'speed' => 30,
            'traits' => [
                [
                    'name' => 'Description',
                    'category' => 'description',
                    'description' => 'Born of dragons, as their name proclaims.',
                    'sort_order' => 0,
                ],
            ],
            'sources' => [
                ['code' => 'PHB', 'pages' => '32'],
            ],
        ];

        $race = $this->importer->import($raceData);

        $this->assertInstanceOf(Race::class, $race);
        $this->assertEquals('Dragonborn', $race->name);
        $this->assertEquals(30, $race->speed);

        // Check traits
        $this->assertCount(1, $race->traits);
        $trait = $race->traits->first();
        $this->assertStringContainsString('Born of dragons', $trait->description);

        // Check relationships
        $this->assertNotNull($race->size);
        $this->assertEquals('M', $race->size->code);
        $this->assertEquals('Medium', $race->size->name);

        // Check sources via entity_sources junction table
        $this->assertEquals(1, $race->sources()->count());
        $entitySource = $race->sources()->first();
        $this->assertEquals('PHB', $entitySource->source->code);
        $this->assertEquals('32', $entitySource->pages);
    }

    #[Test]
    public function it_updates_existing_race_on_reimport()
    {
        $raceData = [
            'name' => 'Dragonborn',
            'size_code' => 'M',
            'speed' => 30,
            'traits' => [
                [
                    'name' => 'Description',
                    'category' => 'description',
                    'description' => 'Original description',
                    'sort_order' => 0,
                ],
            ],
            'sources' => [
                ['code' => 'PHB', 'pages' => '32'],
            ],
        ];

        $this->importer->import($raceData);
        $this->assertEquals(1, Race::count());

        // Re-import with updated traits
        $raceData['traits'][0]['description'] = 'Updated description';
        $race = $this->importer->import($raceData);

        $this->assertEquals(1, Race::count());
        $trait = $race->fresh()->traits->first();
        $this->assertStringContainsString('Updated description', $trait->description);
    }

    #[Test]
    public function it_imports_from_xml_file()
    {
        $xml = <<<'XML'
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

    #[Test]
    public function it_creates_base_race_and_subrace()
    {
        $xml = <<<'XML'
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

    #[Test]
    public function it_creates_only_base_race_when_no_subrace()
    {
        $xml = <<<'XML'
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

    #[Test]
    public function it_reuses_existing_base_race_for_multiple_subraces()
    {
        $xml = <<<'XML'
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

    #[Test]
    public function it_imports_skill_proficiencies()
    {
        $xml = <<<'XML'
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

    #[Test]
    public function it_imports_weapon_proficiencies_as_text()
    {
        $xml = <<<'XML'
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

    #[Test]
    public function it_clears_and_recreates_proficiencies_on_reimport()
    {
        $xml = <<<'XML'
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

    #[Test]
    public function it_imports_race_traits()
    {
        $xml = <<<'XML'
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
    <trait category="species">
      <name>Breath Weapon</name>
      <text>You can exhale destructive energy.</text>
    </trait>
  </race>
</compendium>
XML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'race_test_');
        file_put_contents($tmpFile, $xml);

        $this->importer->importFromFile($tmpFile);

        unlink($tmpFile);

        $race = Race::where('name', 'Dragonborn')->first();
        $this->assertNotNull($race);

        $traits = $race->traits;
        $this->assertCount(2, $traits);

        $descriptionTrait = $traits->where('name', 'Description')->first();
        $this->assertEquals('description', $descriptionTrait->category);
        $this->assertStringContainsString('Born of dragons', $descriptionTrait->description);

        $speciesTrait = $traits->where('name', 'Breath Weapon')->first();
        $this->assertEquals('species', $speciesTrait->category);
    }

    #[Test]
    public function it_imports_ability_score_bonuses_as_modifiers()
    {
        $xml = <<<'XML'
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
</compendium>
XML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'race_test_');
        file_put_contents($tmpFile, $xml);

        $this->importer->importFromFile($tmpFile);

        unlink($tmpFile);

        $race = Race::where('name', 'Dragonborn')->first();
        $modifiers = $race->modifiers;

        $this->assertCount(2, $modifiers);

        $strModifier = $modifiers->where('ability_score_id', AbilityScore::where('code', 'STR')->first()->id)->first();
        $this->assertNotNull($strModifier);
        $this->assertEquals('ability_score', $strModifier->modifier_category);
        $this->assertEquals(2, $strModifier->value);

        $chaModifier = $modifiers->where('ability_score_id', AbilityScore::where('code', 'CHA')->first()->id)->first();
        $this->assertNotNull($chaModifier);
        $this->assertEquals(1, $chaModifier->value);
    }

    #[Test]
    public function it_imports_random_tables_from_trait_rolls_and_links_traits()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Dragonborn</name>
    <size>M</size>
    <speed>30</speed>
    <trait>
      <name>Size</name>
      <text>Your size is Medium.
Source: Player's Handbook (2014) p. 32</text>
      <roll description="Size Modifier">2d8</roll>
    </trait>
  </race>
</compendium>
XML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'race_test_');
        file_put_contents($tmpFile, $xml);

        $this->importer->importFromFile($tmpFile);

        unlink($tmpFile);

        $race = Race::where('name', 'Dragonborn')->first();

        // Random tables are now accessed through traits
        $sizeTrait = $race->traits->where('name', 'Size')->first();
        $this->assertNotNull($sizeTrait);

        $randomTables = $sizeTrait->randomTables;
        $this->assertCount(1, $randomTables);

        $sizeTable = $randomTables->first();
        $this->assertEquals('Size Modifier', $sizeTable->table_name);
        $this->assertEquals('2d8', $sizeTable->dice_type);

        // Verify the random table references the trait
        $this->assertEquals(\App\Models\CharacterTrait::class, $sizeTable->reference_type);
        $this->assertEquals($sizeTrait->id, $sizeTable->reference_id);

        // IMPORTANT: Verify bidirectional link - trait is linked to random table
        $this->assertNotNull($sizeTrait->random_table_id);
        $this->assertEquals($sizeTable->id, $sizeTrait->random_table_id);
    }

    #[Test]
    public function it_imports_multiple_sources()
    {
        $raceData = [
            'name' => 'Test Race',
            'size_code' => 'M',
            'speed' => 30,
            'ability_bonuses' => [],
            'traits' => [],
            'proficiencies' => [],
            'sources' => [
                ['code' => 'PHB', 'pages' => '35'],
                ['code' => 'ERLW', 'pages' => '67'],
            ],
        ];

        $race = $this->importer->import($raceData);

        $fresh = $race->fresh();
        $this->assertCount(2, $fresh->sources);
        $sourceCodes = $fresh->sources->pluck('source.code')->toArray();
        $this->assertContains('PHB', $sourceCodes);
        $this->assertContains('ERLW', $sourceCodes);
    }

    #[Test]
    public function it_imports_ability_choices()
    {
        $raceData = [
            'name' => 'Test Race',
            'size_code' => 'M',
            'speed' => 30,
            'ability_bonuses' => [
                ['ability' => 'Con', 'value' => '+2'],
            ],
            'ability_choices' => [
                [
                    'is_choice' => true,
                    'choice_count' => 1,
                    'value' => 1,
                    'choice_constraint' => 'any',
                ],
            ],
            'traits' => [],
            'proficiencies' => [],
            'sources' => [],
        ];

        $race = $this->importer->import($raceData);

        $choiceModifier = $race->fresh()->modifiers()->where('is_choice', true)->first();
        $this->assertNotNull($choiceModifier);
        $this->assertTrue($choiceModifier->is_choice);
        $this->assertEquals(1, $choiceModifier->choice_count);
        $this->assertEquals('any', $choiceModifier->choice_constraint);
        $this->assertNull($choiceModifier->ability_score_id);
    }

    #[Test]
    public function it_imports_conditions()
    {
        // Create frightened condition if it doesn't exist
        $condition = \App\Models\Condition::firstOrCreate(
            ['slug' => 'frightened'],
            ['name' => 'Frightened', 'description' => 'Test condition']
        );

        $raceData = [
            'name' => 'Test Race',
            'size_code' => 'M',
            'speed' => 30,
            'ability_bonuses' => [],
            'traits' => [],
            'proficiencies' => [],
            'sources' => [],
            'conditions' => [
                [
                    'condition_name' => 'frightened',
                    'effect_type' => 'advantage',
                ],
            ],
        ];

        $race = $this->importer->import($raceData);

        $this->assertCount(1, $race->fresh()->conditions);
        $raceCondition = $race->conditions->first();
        $this->assertEquals('advantage', $raceCondition->effect_type);
        $this->assertEquals($condition->id, $raceCondition->condition_id);
    }

    #[Test]
    public function it_imports_racial_spells()
    {
        // Create a test spell
        $spell = \App\Models\Spell::factory()->create([
            'name' => 'Thaumaturgy',
            'slug' => 'thaumaturgy',
            'level' => 0,
        ]);

        $charisma = \App\Models\AbilityScore::where('code', 'CHA')->first();

        $raceData = [
            'name' => 'Test Race',
            'size_code' => 'M',
            'speed' => 30,
            'ability_bonuses' => [],
            'traits' => [],
            'proficiencies' => [],
            'sources' => [],
            'spellcasting' => [
                'ability' => 'Charisma',
                'spells' => [
                    [
                        'spell_name' => 'Thaumaturgy',
                        'is_cantrip' => true,
                        'level_requirement' => null,
                        'usage_limit' => null,
                    ],
                ],
            ],
        ];

        $race = $this->importer->import($raceData);

        $this->assertCount(1, $race->fresh()->spells);
        $raceSpell = $race->spells->first();
        $this->assertEquals($spell->id, $raceSpell->spell_id);
        $this->assertEquals($charisma->id, $raceSpell->ability_score_id);
        $this->assertTrue($raceSpell->is_cantrip);
    }
}
