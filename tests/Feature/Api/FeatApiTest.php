<?php

namespace Tests\Feature\Api;

use App\Models\Feat;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for Feat API endpoints.
 *
 * These tests use pre-imported data from SearchTestExtension.
 * No RefreshDatabase needed - all tests are read-only against shared data.
 */
#[\PHPUnit\Framework\Attributes\Group('feature-search')]
#[\PHPUnit\Framework\Attributes\Group('search-isolated')]
class FeatApiTest extends TestCase
{
    protected $seed = false;

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
}
