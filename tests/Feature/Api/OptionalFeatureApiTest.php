<?php

namespace Tests\Feature\Api;

use App\Enums\ResourceType;
use App\Models\CharacterClass;
use App\Models\EntityPrerequisite;
use App\Models\EntitySource;
use App\Models\OptionalFeature;
use App\Models\Source;
use Database\Seeders\TestDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for Optional Feature API endpoints.
 *
 * These tests use fixture data and are self-contained.
 */
#[Group('feature-search')]
#[Group('search-isolated')]
class OptionalFeatureApiTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = TestDatabaseSeeder::class;

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
        $feature = OptionalFeature::factory()->create(['slug' => 'test:of-sources-'.uniqid()]);
        $source = Source::firstOrFail();
        EntitySource::factory()->forEntity(OptionalFeature::class, $feature->id)->create([
            'source_id' => $source->id,
            'pages' => '42',
        ]);

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
        $feature = OptionalFeature::factory()->create(['slug' => 'test:of-classes-'.uniqid()]);
        $class = CharacterClass::firstOrFail();
        $feature->classes()->attach($class->id);

        $response = $this->getJson("/api/v1/optional-features/{$feature->slug}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'classes' => [
                    '*' => ['slug', 'name'],
                ],
            ],
        ]);
        $this->assertGreaterThan(0, count($response->json('data.classes')));
    }

    #[Test]
    public function optional_feature_includes_spell_mechanics_when_applicable(): void
    {
        $feature = OptionalFeature::factory()->create([
            'slug' => 'test:of-spell-mech-'.uniqid(),
            'casting_time' => '1 action',
            'range' => '60 feet',
            'duration' => 'Instantaneous',
        ]);

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
        $feature = OptionalFeature::factory()->create([
            'slug' => 'test:of-resource-'.uniqid(),
            'resource_type' => ResourceType::SUPERIORITY_DIE,
            'resource_cost' => 1,
        ]);

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

    #[Test]
    public function optional_feature_includes_prerequisites_in_response(): void
    {
        $feature = OptionalFeature::factory()->create(['slug' => 'test:of-prereq-'.uniqid()]);
        EntityPrerequisite::factory()->forEntity(OptionalFeature::class, $feature->id)->create();

        $response = $this->getJson("/api/v1/optional-features/{$feature->slug}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'prerequisites' => [
                    '*' => [
                        'id',
                        'prerequisite_type',
                        'prerequisite_id',
                        'minimum_value',
                        'description',
                        'group_id',
                    ],
                ],
            ],
        ]);
    }

    #[Test]
    public function optional_feature_with_class_prerequisite_includes_class_resource(): void
    {
        $feature = OptionalFeature::factory()->create(['slug' => 'test:of-class-prereq-'.uniqid()]);
        $class = CharacterClass::firstOrFail();
        EntityPrerequisite::factory()->forEntity(OptionalFeature::class, $feature->id)->create([
            'prerequisite_type' => CharacterClass::class,
            'prerequisite_id' => $class->id,
        ]);

        $response = $this->getJson("/api/v1/optional-features/{$feature->slug}");

        $response->assertOk();

        $classPrereq = collect($response->json('data.prerequisites'))
            ->firstWhere('prerequisite_type', CharacterClass::class);

        $this->assertNotNull($classPrereq, 'Should have class prerequisite');
        $this->assertArrayHasKey('class', $classPrereq, 'Class prerequisite should include class resource');
        $this->assertArrayHasKey('slug', $classPrereq['class'], 'Class resource should have slug');
        $this->assertArrayHasKey('name', $classPrereq['class'], 'Class resource should have name');
    }

    #[Test]
    public function optional_feature_without_prerequisites_returns_empty_array(): void
    {
        // Factory default creates an OptionalFeature with no prerequisites.
        $feature = OptionalFeature::factory()->create(['slug' => 'test:of-no-prereq-'.uniqid()]);

        $response = $this->getJson("/api/v1/optional-features/{$feature->slug}");

        $response->assertOk();
        $response->assertJsonPath('data.prerequisites', []);
    }
}
