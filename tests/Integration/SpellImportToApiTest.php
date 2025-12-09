<?php

namespace Tests\Integration;

use App\Services\Importers\SpellImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-search')]
#[\PHPUnit\Framework\Attributes\Group('search-imported')]
class SpellImportToApiTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = \Database\Seeders\TestDatabaseSeeder::class;

    #[Test]
    public function spell_import_to_api_pipeline(): void
    {
        // Import from actual XML file
        $importer = new SpellImporter;
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
                        'sources' => [
                            '*' => ['code', 'name', 'pages'],
                        ],
                    ],
                ],
            ]);

        // Test search - use a spell we know exists from fixtures
        $spell = \App\Models\Spell::first();
        if ($spell) {
            $searchResponse = $this->getJson('/api/v1/spells?q='.urlencode($spell->name));
            $searchResponse->assertStatus(200);
        }
    }
}
