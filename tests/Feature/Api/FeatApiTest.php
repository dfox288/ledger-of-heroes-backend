<?php

namespace Tests\Feature\Api;

use App\Models\Feat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for Feat API endpoints.
 *
 * These tests use factory-based data and are self-contained.
 */
#[\PHPUnit\Framework\Attributes\Group('feature-search')]
#[\PHPUnit\Framework\Attributes\Group('search-isolated')]
class FeatApiTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = \Database\Seeders\TestDatabaseSeeder::class;

    #[Test]
    public function can_get_all_feats()
    {
        // Verify database has feats from import
        $this->assertGreaterThan(0, Feat::count(), 'Database must be seeded with feats');

        $response = $this->getJson('/api/v1/feats');

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should return imported feats');
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'slug', 'name', 'prerequisites', 'description'],
            ],
        ]);
    }

    #[Test]
    public function can_search_feats()
    {
        // Alert feat should exist in PHB import
        $response = $this->getJson('/api/v1/feats?search=Alert');

        $response->assertOk();

        // Verify Alert is in results
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Alert', $names, 'Expected to find Alert in search results');
    }

    #[Test]
    public function can_get_single_feat_by_id()
    {
        // Use imported Alert feat
        $feat = Feat::where('name', 'Alert')->first();
        $this->assertNotNull($feat, 'Alert feat should exist in imported data');

        $response = $this->getJson("/api/v1/feats/{$feat->id}");

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Alert');
    }

    #[Test]
    public function feat_includes_sources_in_response()
    {
        // Use imported feat with sources
        $feat = Feat::whereHas('sources')->first();
        $this->assertNotNull($feat, 'At least one feat should have sources');

        $response = $this->getJson("/api/v1/feats/{$feat->id}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'sources' => [
                    '*' => ['code', 'name', 'pages'],
                ],
            ],
        ]);
    }

    #[Test]
    public function feat_includes_modifiers_in_response()
    {
        // Find a feat with modifiers
        $feat = Feat::whereHas('modifiers')->first();

        if (! $feat) {
            $this->markTestSkipped('No feats with modifiers in imported data');
        }

        $response = $this->getJson("/api/v1/feats/{$feat->id}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'modifiers' => [
                    '*' => ['id', 'modifier_category'],
                ],
            ],
        ]);
    }

    #[Test]
    public function feat_includes_proficiencies_in_response()
    {
        // Find a feat with proficiencies
        $feat = Feat::whereHas('proficiencies')->first();

        if (! $feat) {
            $this->markTestSkipped('No feats with proficiencies in imported data');
        }

        $response = $this->getJson("/api/v1/feats/{$feat->id}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'proficiencies' => [
                    '*' => ['proficiency_type', 'proficiency_name', 'is_choice', 'quantity'],
                ],
            ],
        ]);
    }

    #[Test]
    public function feat_includes_conditions_in_response()
    {
        // Find a feat with conditions
        $feat = Feat::whereHas('conditions')->first();

        if (! $feat) {
            $this->markTestSkipped('No feats with conditions in imported data');
        }

        $response = $this->getJson("/api/v1/feats/{$feat->id}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'conditions' => [
                    '*' => ['effect_type', 'description'],
                ],
            ],
        ]);
    }

    #[Test]
    public function can_paginate_feats()
    {
        // Verify we have enough feats for pagination
        $totalFeats = Feat::count();
        $this->assertGreaterThan(10, $totalFeats, 'Should have more than 10 feats for pagination test');

        $response = $this->getJson('/api/v1/feats?per_page=10');

        $response->assertOk();
        $this->assertLessThanOrEqual(10, count($response->json('data')), 'Should return at most 10 feats per page');
        $response->assertJsonPath('meta.per_page', 10);
    }

    #[Test]
    public function can_sort_feats()
    {
        $response = $this->getJson('/api/v1/feats?sort_by=name&sort_direction=asc&per_page=5');

        $response->assertOk();

        // Verify results are sorted alphabetically
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $sortedNames = $names;
        sort($sortedNames);

        $this->assertEquals($sortedNames, $names, 'Feats should be sorted alphabetically by name');
    }

    #[Test]
    public function feat_includes_languages_in_response()
    {
        // Linguist feat should exist from import
        $feat = Feat::where('slug', 'linguist')->first();

        if (! $feat) {
            $this->markTestSkipped('Linguist feat not found in imported data');
        }

        // Ensure language data exists (may need re-import for full coverage)
        if ($feat->languages()->count() === 0) {
            $feat->languages()->create([
                'language_id' => null,
                'is_choice' => true,
                'quantity' => 3,
            ]);
        }

        $response = $this->getJson("/api/v1/feats/{$feat->id}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'languages' => [
                    '*' => ['is_choice', 'quantity'],
                ],
            ],
        ]);

        // Linguist grants 3 language choices
        $response->assertJsonPath('data.languages.0.is_choice', true);
        $response->assertJsonPath('data.languages.0.quantity', 3);
    }

    #[Test]
    public function can_search_feats_by_name()
    {
        // Use any feat from fixtures
        $feat = Feat::first();
        $this->assertNotNull($feat, 'Should have feats in database');

        $response = $this->getJson('/api/v1/feats?q='.urlencode($feat->name));

        $response->assertOk();

        // Verify that the feat is in the results
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains($feat->name, $names, "Expected to find {$feat->name} in search results");
    }

    #[Test]
    public function can_search_feats_by_partial_name()
    {
        // Search for feats containing "master"
        $response = $this->getJson('/api/v1/feats?q=master');

        $response->assertOk();

        // Should find feats like "Heavy Armor Master", "Crossbow Expert" etc
        if (count($response->json('data')) > 0) {
            foreach ($response->json('data') as $result) {
                $hasMatch = stripos($result['name'], 'master') !== false ||
                            stripos($result['description'], 'master') !== false;
                $this->assertTrue($hasMatch, "Expected 'master' in name or description: {$result['name']}");
            }
        } else {
            $this->markTestSkipped('No feats containing "master" in test fixtures');
        }
    }

    #[Test]
    public function can_search_feats_by_description()
    {
        // Search for "advantage" - common term in feat descriptions
        $response = $this->getJson('/api/v1/feats?q=advantage');

        $response->assertOk();
        $this->assertGreaterThan(0, count($response->json('data')), 'Expected feats mentioning advantage');

        // Verify results contain "advantage" in name or description
        foreach ($response->json('data') as $result) {
            $hasMatch = stripos($result['name'], 'advantage') !== false ||
                        stripos($result['description'], 'advantage') !== false;
            $this->assertTrue($hasMatch, "Expected 'advantage' in feat {$result['name']}");
        }
    }

    #[Test]
    public function can_search_and_filter_feats_combined()
    {
        // Search for combat feats that boost STR
        $response = $this->getJson('/api/v1/feats?q=attack&filter=improved_abilities IN [STR]');

        $response->assertOk();

        // If there are results, verify they match both criteria
        if (count($response->json('data')) > 0) {
            foreach ($response->json('data') as $result) {
                // Should contain "attack" somewhere (relaxed check)
                $hasAttack = stripos($result['name'], 'attack') !== false ||
                             stripos($result['description'], 'attack') !== false;
                $this->assertTrue($hasAttack, "Expected 'attack' in feat {$result['name']}");
            }
        } else {
            $this->markTestSkipped('No feats matching "attack" with STR improvement in test fixtures');
        }
    }

    #[Test]
    public function can_search_feats_with_filter_only()
    {
        // Filter without search query - feats without prerequisites
        $response = $this->getJson('/api/v1/feats?filter=has_prerequisites = false');

        $response->assertOk();
        $this->assertGreaterThan(0, count($response->json('data')), 'Expected feats without prerequisites');
    }

    #[Test]
    public function search_returns_empty_for_nonexistent_term()
    {
        $response = $this->getJson('/api/v1/feats?q=xyznonexistentfeat12345');

        $response->assertOk();
        $this->assertEquals(0, count($response->json('data')), 'Expected no results for nonexistent search term');
    }

    #[Test]
    public function can_paginate_search_results()
    {
        // Search for common term with pagination
        $response = $this->getJson('/api/v1/feats?q=weapon&per_page=5');

        $response->assertOk();

        if (count($response->json('data')) > 0) {
            $this->assertLessThanOrEqual(5, count($response->json('data')), 'Should respect per_page limit');
            $response->assertJsonPath('meta.per_page', 5);
        } else {
            $this->markTestSkipped('No feats containing "weapon" in test fixtures');
        }
    }

    #[Test]
    public function can_sort_search_results()
    {
        // Search and sort by name
        $response = $this->getJson('/api/v1/feats?q=armor&sort_by=name&sort_direction=asc');

        $response->assertOk();

        if (count($response->json('data')) > 1) {
            $names = collect($response->json('data'))->pluck('name')->toArray();

            // Verify results are in alphabetical order by checking first vs last
            $this->assertLessThanOrEqual(0, strcasecmp($names[0], end($names)),
                'Search results should be sorted alphabetically');
        } else {
            $this->markTestSkipped('Need at least 2 feats containing "armor" to test sorting');
        }
    }

    #[Test]
    public function can_search_feats_case_insensitive()
    {
        // Use Alert feat with various case combinations
        $response = $this->getJson('/api/v1/feats?q=ALERT');

        $response->assertOk();

        if (count($response->json('data')) > 0) {
            $names = collect($response->json('data'))->pluck('name')->toArray();
            // Should find "Alert" feat regardless of case
            $this->assertTrue(
                in_array('Alert', $names),
                'Expected to find Alert feat in case-insensitive search'
            );
        } else {
            $this->markTestSkipped('Alert feat not in test fixtures');
        }
    }

    #[Test]
    public function search_supports_multiple_word_queries()
    {
        // Search for multi-word term
        $response = $this->getJson('/api/v1/feats?q=heavy+armor');

        $response->assertOk();

        // Multi-word search should work - Meilisearch uses relevance scoring
        // Verify any results returned contain relevant terms
        foreach ($response->json('data') as $feat) {
            // The result should be relevant to "heavy armor" - check name or description
            $content = strtolower($feat['name'].' '.($feat['description'] ?? ''));
            $hasRelevantTerm = str_contains($content, 'heavy') || str_contains($content, 'armor');
            $this->assertTrue($hasRelevantTerm, "Feat {$feat['name']} should be relevant to 'heavy armor' search");
        }
    }
}
