<?php

namespace Tests\Unit\Services;

use App\Models\Race;
use App\Services\Importers\RaceImporter;
use App\Services\Parsers\RaceXmlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RaceImporterTest extends TestCase
{
    use RefreshDatabase;

    private RaceImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = new RaceImporter(new RaceXmlParser());
    }

    public function test_imports_race_from_parsed_data(): void
    {
        $data = [
            'name' => 'Test Race',
            'size_code' => 'M',
            'speed' => 30,
            'source_code' => 'PHB',
            'source_page' => 100,
            'traits' => [],
            'modifiers' => [],
            'proficiencies' => [],
        ];

        $race = $this->importer->importFromParsedData($data);

        $this->assertInstanceOf(Race::class, $race);
        $this->assertEquals('Test Race', $race->name);
        $this->assertEquals(30, $race->speed);
    }

    public function test_imports_race_with_modifiers_and_traits(): void
    {
        $data = [
            'name' => 'Dragonborn',
            'size_code' => 'M',
            'speed' => 30,
            'source_code' => 'PHB',
            'source_page' => 32,
            'traits' => [
                ['name' => 'Description', 'category' => 'description', 'description' => 'Born of dragons'],
            ],
            'modifiers' => [
                ['modifier_type' => 'ability_score', 'target' => 'strength', 'value' => '+2'],
            ],
            'proficiencies' => [],
        ];

        $race = $this->importer->importFromParsedData($data);

        $this->assertCount(1, $race->traits);
        $this->assertCount(1, $race->modifiers);
    }
}
