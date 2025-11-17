<?php

namespace Tests\Integration;

use App\Services\Importers\SpellImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpellImportToApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_spell_import_to_api_pipeline(): void
    {
        // Import from actual XML file
        $importer = new SpellImporter();
        $count = $importer->importFromFile(base_path('import-files/spells-phb.xml'));

        $this->assertGreaterThan(0, $count, 'Should import at least one spell');

        // Verify via API
        $response = $this->getJson('/api/v1/spells');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'level',
                        'school',
                        'casting_time',
                        'range',
                        'components',
                        'duration',
                        'needs_concentration',
                        'is_ritual',
                        'description',
                        'source',
                        'source_pages',
                    ]
                ]
            ]);

        // Test search
        $searchResponse = $this->getJson('/api/v1/spells?search=fireball');
        $searchResponse->assertStatus(200);

        if ($searchResponse->json('meta.total') > 0) {
            $spell = $searchResponse->json('data.0');
            $this->assertStringContainsStringIgnoringCase('fire', $spell['name'] . ' ' . $spell['description']);
        }
    }
}
