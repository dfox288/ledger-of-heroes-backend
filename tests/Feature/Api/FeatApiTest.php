<?php

namespace Tests\Feature\Api;

use App\Models\EntityCondition;
use App\Models\EntityLanguage;
use App\Models\Feat;
use App\Models\Language;
use App\Models\Modifier;
use App\Models\Proficiency;
use Database\Seeders\TestDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for Feat API endpoints.
 *
 * These tests use factory-based data and are self-contained.
 */
#[Group('feature-search')]
#[Group('search-isolated')]
class FeatApiTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = TestDatabaseSeeder::class;

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
        $feat = Feat::factory()->create();
        Modifier::factory()->forEntity(Feat::class, $feat->id)->create();

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
        $feat = Feat::factory()->create();
        Proficiency::factory()->forEntity(Feat::class, $feat->id)->create();

        $response = $this->getJson("/api/v1/feats/{$feat->id}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                // Note: is_choice and quantity were dropped from entity_proficiencies by
                // migration 2025_01_01_000021. Choices now live in entity_choices.
                'proficiencies' => [
                    '*' => ['proficiency_type', 'proficiency_name', 'grants'],
                ],
            ],
        ]);
    }

    #[Test]
    public function feat_includes_conditions_in_response()
    {
        $feat = Feat::factory()->create();
        EntityCondition::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'condition_id' => null,
            'effect_type' => 'advantage',
            'description' => 'Advantage on saving throws against being charmed.',
        ]);

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
        // Note: language CHOICES now live in entity_choices (via EntityChoiceResource).
        // entity_languages only holds fixed language grants. Test the fixed-grant path.
        $feat = Feat::factory()->create();
        $language = Language::factory()->create();
        EntityLanguage::factory()->forEntity(Feat::class, $feat->id)
            ->withLanguage($language->id)
            ->create();

        $response = $this->getJson("/api/v1/feats/{$feat->id}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'languages' => [
                    '*' => ['language' => ['id', 'name']],
                ],
            ],
        ]);
        $response->assertJsonPath('data.languages.0.language.id', $language->id);
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
