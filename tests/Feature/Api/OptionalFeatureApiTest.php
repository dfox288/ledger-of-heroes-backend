<?php

namespace Tests\Feature\Api;

use App\Models\OptionalFeature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for Optional Feature API endpoints.
 *
 * These tests use fixture data and are self-contained.
 */
#[\PHPUnit\Framework\Attributes\Group('feature-search')]
#[\PHPUnit\Framework\Attributes\Group('search-isolated')]
class OptionalFeatureApiTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = \Database\Seeders\TestDatabaseSeeder::class;

    #[Test]
    public function can_get_all_optional_features(): void
    {
        $this->assertGreaterThan(0, OptionalFeature::count(), 'Database must be seeded with optional features');

        $response = $this->getJson('/api/v1/optional-features');

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should return imported optional features');
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'slug', 'name', 'feature_type', 'description'],
            ],
        ]);
    }

    #[Test]
    public function can_get_single_optional_feature_by_slug(): void
    {
        $feature = OptionalFeature::first();
        $this->assertNotNull($feature, 'At least one optional feature should exist');

        $response = $this->getJson("/api/v1/optional-features/{$feature->slug}");

        $response->assertOk();
        $response->assertJsonPath('data.slug', $feature->slug);
        $response->assertJsonPath('data.name', $feature->name);
    }

    #[Test]
    public function optional_feature_includes_feature_type_with_label(): void
    {
        $feature = OptionalFeature::first();

        $response = $this->getJson("/api/v1/optional-features/{$feature->slug}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'feature_type',
                'feature_type_label',
            ],
        ]);
        $this->assertNotNull($response->json('data.feature_type'));
        $this->assertNotNull($response->json('data.feature_type_label'));
    }

    #[Test]
    public function optional_feature_includes_sources_in_response(): void
    {
        $feature = OptionalFeature::whereHas('sources')->first();

        if (! $feature) {
            $this->markTestSkipped('No optional features with sources in fixture data');
        }

        $response = $this->getJson("/api/v1/optional-features/{$feature->slug}");

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
    public function optional_feature_includes_classes_in_response(): void
    {
        $feature = OptionalFeature::whereHas('classes')->first();

        if (! $feature) {
            $this->markTestSkipped('No optional features with classes in fixture data');
        }

        $response = $this->getJson("/api/v1/optional-features/{$feature->slug}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'classes' => [
                    '*' => ['slug', 'name'],
                ],
            ],
        ]);
    }

    #[Test]
    public function optional_feature_includes_spell_mechanics_when_applicable(): void
    {
        // Find an elemental discipline or similar with spell mechanics
        $feature = OptionalFeature::whereNotNull('casting_time')->first();

        if (! $feature) {
            $this->markTestSkipped('No optional features with spell mechanics in fixture data');
        }

        $response = $this->getJson("/api/v1/optional-features/{$feature->slug}");

        $response->assertOk();
        $response->assertJsonPath('data.has_spell_mechanics', true);
        $response->assertJsonStructure([
            'data' => [
                'casting_time',
                'range',
                'duration',
            ],
        ]);
    }

    #[Test]
    public function optional_feature_includes_resource_cost_when_applicable(): void
    {
        $feature = OptionalFeature::whereNotNull('resource_type')->first();

        if (! $feature) {
            $this->markTestSkipped('No optional features with resource costs in fixture data');
        }

        $response = $this->getJson("/api/v1/optional-features/{$feature->slug}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'resource_type',
                'resource_cost',
            ],
        ]);
    }

    #[Test]
    public function can_paginate_optional_features(): void
    {
        $totalFeatures = OptionalFeature::count();
        $this->assertGreaterThan(10, $totalFeatures, 'Should have more than 10 optional features for pagination test');

        $response = $this->getJson('/api/v1/optional-features?per_page=10');

        $response->assertOk();
        $this->assertLessThanOrEqual(10, count($response->json('data')));
        $response->assertJsonPath('meta.per_page', 10);
    }

    #[Test]
    public function can_sort_optional_features_by_name(): void
    {
        $response = $this->getJson('/api/v1/optional-features?sort_by=name&sort_direction=asc&per_page=5');

        $response->assertOk();

        $names = collect($response->json('data'))->pluck('name')->toArray();
        $sortedNames = $names;
        sort($sortedNames);

        $this->assertEquals($sortedNames, $names, 'Optional features should be sorted alphabetically by name');
    }

    #[Test]
    public function can_sort_optional_features_by_level_requirement(): void
    {
        $response = $this->getJson('/api/v1/optional-features?sort_by=level_requirement&sort_direction=asc&per_page=20');

        $response->assertOk();

        $levels = collect($response->json('data'))
            ->pluck('level_requirement')
            ->filter() // Remove nulls
            ->values()
            ->toArray();

        if (count($levels) < 2) {
            $this->markTestSkipped('Not enough optional features with level requirements for sort test');
        }

        $sortedLevels = $levels;
        sort($sortedLevels);

        $this->assertEquals($sortedLevels, $levels, 'Optional features should be sorted by level requirement');
    }

    #[Test]
    public function returns_404_for_nonexistent_optional_feature(): void
    {
        $response = $this->getJson('/api/v1/optional-features/nonexistent-feature-slug');

        $response->assertNotFound();
    }

    #[Test]
    public function optional_feature_type_lookup_returns_all_types(): void
    {
        $response = $this->getJson('/api/v1/lookups/optional-feature-types');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => ['value', 'label'],
            ],
        ]);

        // Should have 8 feature types
        $types = collect($response->json('data'))->pluck('value')->toArray();
        $this->assertContains('eldritch_invocation', $types);
        $this->assertContains('maneuver', $types);
        $this->assertContains('metamagic', $types);
        $this->assertContains('fighting_style', $types);
    }

    #[Test]
    public function can_search_optional_features_by_name(): void
    {
        // Get a feature name to search for
        $feature = OptionalFeature::first();
        $searchTerm = substr($feature->name, 0, 5);

        $response = $this->getJson("/api/v1/optional-features?q={$searchTerm}");

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find results matching search term');
    }
}
