<?php

namespace Tests\Feature\Importers;

use App\Models\Race;
use App\Models\Size;
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

        $this->assertEquals(2, $count);
        $this->assertEquals(2, Race::count());
        $this->assertDatabaseHas('races', ['name' => 'Dragonborn']);
        $this->assertDatabaseHas('races', ['name' => 'Hill']);
    }
}
