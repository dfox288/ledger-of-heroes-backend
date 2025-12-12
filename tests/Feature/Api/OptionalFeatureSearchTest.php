<?php

namespace Tests\Feature\Api;

use App\Models\OptionalFeature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\WaitsForMeilisearch;
use Tests\TestCase;

/**
 * Tests for Optional Feature search functionality using Meilisearch.
 *
 * These tests use fixture data and are self-contained.
 */
#[\PHPUnit\Framework\Attributes\Group('feature-search')]
class OptionalFeatureSearchTest extends TestCase
{
    use RefreshDatabase;
    use WaitsForMeilisearch;

    protected $seeder = \Database\Seeders\TestDatabaseSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('search:configure-indexes');
    }

    #[Test]
    public function it_searches_optional_features_by_name(): void
    {
        // Get a feature name to search for
        $feature = OptionalFeature::first();
        $this->assertNotNull($feature, 'At least one optional feature should exist');

        // Search for the first word of the name
        $searchTerm = explode(' ', $feature->name)[0];

        $response = $this->getJson("/api/v1/optional-features?q={$searchTerm}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['*' => ['id', 'name', 'description']],
                'meta',
            ]);

        // At least one result should match
        $this->assertGreaterThan(0, $response->json('meta.total'));
    }

    #[Test]
    public function it_validates_search_query_minimum_length(): void
    {
        $response = $this->getJson('/api/v1/optional-features?q=a');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    }

    #[Test]
    public function it_handles_empty_search_query_gracefully(): void
    {
        $response = $this->getJson('/api/v1/optional-features');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta'])
            ->assertJsonPath('meta.total', OptionalFeature::count());
    }

    #[Test]
    public function it_searches_optional_features_by_description(): void
    {
        // Find a feature with a distinct word in the description
        $feature = OptionalFeature::whereNotNull('description')
            ->where('description', '!=', '')
            ->first();

        if (! $feature) {
            $this->markTestSkipped('No optional features with descriptions in fixture data');
        }

        // Extract a word from the description that's likely unique
        $words = preg_split('/\s+/', strip_tags($feature->description));
        $searchWord = collect($words)
            ->filter(fn ($w) => strlen($w) > 5)
            ->first();

        if (! $searchWord) {
            $this->markTestSkipped('Could not find a suitable search word in description');
        }

        $response = $this->getJson("/api/v1/optional-features?q={$searchWord}");

        $response->assertOk();

        // We found a feature with this word, so search should return at least that feature
        // If Meilisearch is indexed, verify results are relevant
        foreach ($response->json('data') as $result) {
            // Results should be relevant to the search term
            $content = strtolower($result['name'].' '.($result['description'] ?? ''));
            $this->assertTrue(
                str_contains($content, strtolower($searchWord)),
                "Result {$result['name']} should contain search term '{$searchWord}'"
            );
        }
    }

    #[Test]
    public function it_combines_search_with_filter(): void
    {
        // Search with a filter
        $feature = OptionalFeature::first();
        $searchTerm = substr($feature->name, 0, 4);

        $response = $this->getJson("/api/v1/optional-features?q={$searchTerm}&filter=feature_type = {$feature->feature_type->value}");

        $response->assertOk();
        // All results should match the feature type
        foreach ($response->json('data') as $resultFeature) {
            $this->assertEquals($feature->feature_type->value, $resultFeature['feature_type']);
        }
    }

    #[Test]
    public function it_returns_paginated_search_results(): void
    {
        $response = $this->getJson('/api/v1/optional-features?per_page=5');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
                'links',
            ])
            ->assertJsonPath('meta.per_page', 5);

        $this->assertLessThanOrEqual(5, count($response->json('data')));
    }

    #[Test]
    public function it_handles_special_characters_in_search(): void
    {
        // Search with special characters should not cause errors
        $response = $this->getJson('/api/v1/optional-features?q=blast+fire');

        $response->assertOk();
    }

    #[Test]
    public function it_sorts_search_results(): void
    {
        $response = $this->getJson('/api/v1/optional-features?sort_by=name&sort_direction=asc&per_page=10');

        $response->assertOk();

        $names = collect($response->json('data'))->pluck('name')->toArray();
        $sortedNames = $names;
        sort($sortedNames);

        $this->assertEquals($sortedNames, $names);
    }
}
