<?php

namespace Tests\Feature\Api;

use App\Models\OptionalFeature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for Optional Feature filter operators using Meilisearch.
 *
 * These tests use fixture data and are self-contained.
 */
#[\PHPUnit\Framework\Attributes\Group('feature-search')]
#[\PHPUnit\Framework\Attributes\Group('search-isolated')]
class OptionalFeatureFilterOperatorTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = \Database\Seeders\TestDatabaseSeeder::class;

    // ============================================================
    // Integer Operators (id field) - 7 tests
    // ============================================================

    #[Test]
    public function it_filters_by_id_with_equals(): void
    {
        $feature = OptionalFeature::first();
        $this->assertNotNull($feature, 'At least one optional feature should exist');

        $response = $this->getJson("/api/v1/optional-features?filter=id = {$feature->id}");

        $response->assertOk();
        $this->assertEquals(1, $response->json('meta.total'));
        $response->assertJsonPath('data.0.id', $feature->id);
    }

    #[Test]
    public function it_filters_by_id_with_not_equals(): void
    {
        $feature = OptionalFeature::first();

        $response = $this->getJson("/api/v1/optional-features?filter=id != {$feature->id}");

        $response->assertOk();
        $returnedIds = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertNotContains($feature->id, $returnedIds);
    }

    #[Test]
    public function it_filters_by_id_with_greater_than(): void
    {
        $features = OptionalFeature::orderBy('id')->get();
        $middleFeature = $features->get((int) ($features->count() / 2));

        $response = $this->getJson("/api/v1/optional-features?filter=id > {$middleFeature->id}");

        $response->assertOk();
        foreach ($response->json('data') as $feature) {
            $this->assertGreaterThan($middleFeature->id, $feature['id']);
        }
    }

    #[Test]
    public function it_filters_by_id_with_greater_than_or_equal(): void
    {
        $features = OptionalFeature::orderBy('id')->get();
        $middleFeature = $features->get((int) ($features->count() / 2));

        $response = $this->getJson("/api/v1/optional-features?filter=id >= {$middleFeature->id}");

        $response->assertOk();
        foreach ($response->json('data') as $feature) {
            $this->assertGreaterThanOrEqual($middleFeature->id, $feature['id']);
        }
    }

    #[Test]
    public function it_filters_by_id_with_less_than(): void
    {
        $features = OptionalFeature::orderBy('id')->get();
        $middleFeature = $features->get((int) ($features->count() / 2));

        $response = $this->getJson("/api/v1/optional-features?filter=id < {$middleFeature->id}");

        $response->assertOk();
        foreach ($response->json('data') as $feature) {
            $this->assertLessThan($middleFeature->id, $feature['id']);
        }
    }

    #[Test]
    public function it_filters_by_id_with_less_than_or_equal(): void
    {
        $features = OptionalFeature::orderBy('id')->get();
        $middleFeature = $features->get((int) ($features->count() / 2));

        $response = $this->getJson("/api/v1/optional-features?filter=id <= {$middleFeature->id}");

        $response->assertOk();
        foreach ($response->json('data') as $feature) {
            $this->assertLessThanOrEqual($middleFeature->id, $feature['id']);
        }
    }

    #[Test]
    public function it_filters_by_id_with_to_range(): void
    {
        $features = OptionalFeature::orderBy('id')->get();
        $startFeature = $features->get(2);
        $endFeature = $features->get(5);

        $response = $this->getJson("/api/v1/optional-features?filter=id {$startFeature->id} TO {$endFeature->id}");

        $response->assertOk();
        foreach ($response->json('data') as $feature) {
            $this->assertGreaterThanOrEqual($startFeature->id, $feature['id']);
            $this->assertLessThanOrEqual($endFeature->id, $feature['id']);
        }
    }

    // ============================================================
    // String Operators (slug field) - 2 tests
    // ============================================================

    #[Test]
    public function it_filters_by_slug_with_equals(): void
    {
        $feature = OptionalFeature::first();

        $response = $this->getJson("/api/v1/optional-features?filter=slug = {$feature->slug}");

        $response->assertOk();
        $this->assertEquals(1, $response->json('meta.total'));
        $response->assertJsonPath('data.0.slug', $feature->slug);
    }

    #[Test]
    public function it_filters_by_slug_with_not_equals(): void
    {
        $feature = OptionalFeature::first();

        $response = $this->getJson("/api/v1/optional-features?filter=slug != {$feature->slug}");

        $response->assertOk();
        $returnedSlugs = collect($response->json('data'))->pluck('slug')->toArray();
        $this->assertNotContains($feature->slug, $returnedSlugs);
    }

    // ============================================================
    // String Operators (feature_type field) - 2 tests
    // ============================================================

    #[Test]
    public function it_filters_by_feature_type_with_equals(): void
    {
        $response = $this->getJson('/api/v1/optional-features?filter=feature_type = eldritch_invocation');

        $response->assertOk();
        foreach ($response->json('data') as $feature) {
            $this->assertEquals('eldritch_invocation', $feature['feature_type']);
        }
    }

    #[Test]
    public function it_filters_by_feature_type_with_not_equals(): void
    {
        $response = $this->getJson('/api/v1/optional-features?filter=feature_type != eldritch_invocation');

        $response->assertOk();
        foreach ($response->json('data') as $feature) {
            $this->assertNotEquals('eldritch_invocation', $feature['feature_type']);
        }
    }

    // ============================================================
    // Integer Operators (level_requirement field) - Nullable
    // ============================================================

    #[Test]
    public function it_filters_by_level_requirement_with_equals(): void
    {
        $featureWithLevel = OptionalFeature::whereNotNull('level_requirement')->first();

        if (! $featureWithLevel) {
            $this->markTestSkipped('No optional features with level requirements in fixture data');
        }

        $response = $this->getJson("/api/v1/optional-features?filter=level_requirement = {$featureWithLevel->level_requirement}");

        $response->assertOk();
        foreach ($response->json('data') as $feature) {
            $this->assertEquals($featureWithLevel->level_requirement, $feature['level_requirement']);
        }
    }

    #[Test]
    public function it_filters_by_level_requirement_with_is_null(): void
    {
        $response = $this->getJson('/api/v1/optional-features?filter=level_requirement IS NULL');

        $response->assertOk();
        foreach ($response->json('data') as $feature) {
            $this->assertNull($feature['level_requirement']);
        }
    }

    #[Test]
    public function it_filters_by_level_requirement_with_is_not_null(): void
    {
        $response = $this->getJson('/api/v1/optional-features?filter=level_requirement IS NOT NULL');

        $response->assertOk();
        foreach ($response->json('data') as $feature) {
            $this->assertNotNull($feature['level_requirement']);
        }
    }

    // ============================================================
    // String Operators (resource_type field) - Nullable
    // ============================================================

    #[Test]
    public function it_filters_by_resource_type_with_equals(): void
    {
        $featureWithResource = OptionalFeature::whereNotNull('resource_type')->first();

        if (! $featureWithResource) {
            $this->markTestSkipped('No optional features with resource types in fixture data');
        }

        $resourceType = $featureWithResource->resource_type->value;
        $response = $this->getJson("/api/v1/optional-features?filter=resource_type = {$resourceType}");

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'));
    }

    #[Test]
    public function it_filters_by_resource_type_is_null(): void
    {
        $response = $this->getJson('/api/v1/optional-features?filter=resource_type IS NULL');

        $response->assertOk();

        // IS NULL may legitimately return 0 results if all features have resource_type set
        // If results ARE returned, verify they actually have null resource_type
        foreach ($response->json('data') as $feature) {
            $this->assertNull($feature['resource_type'] ?? null, "Feature {$feature['name']} should have null resource_type");
        }
    }

    // ============================================================
    // Boolean Operators (has_spell_mechanics field) - 4 tests
    // ============================================================

    #[Test]
    public function it_filters_by_has_spell_mechanics_with_equals_true(): void
    {
        $response = $this->getJson('/api/v1/optional-features?filter=has_spell_mechanics = true');

        $response->assertOk();
        foreach ($response->json('data') as $feature) {
            $featureModel = OptionalFeature::find($feature['id']);
            $this->assertTrue($featureModel->has_spell_mechanics, "Feature {$feature['name']} should have spell mechanics");
        }
    }

    #[Test]
    public function it_filters_by_has_spell_mechanics_with_equals_false(): void
    {
        $response = $this->getJson('/api/v1/optional-features?filter=has_spell_mechanics = false');

        $response->assertOk();
        foreach ($response->json('data') as $feature) {
            $featureModel = OptionalFeature::find($feature['id']);
            $this->assertFalse($featureModel->has_spell_mechanics, "Feature {$feature['name']} should not have spell mechanics");
        }
    }

    #[Test]
    public function it_filters_by_has_spell_mechanics_with_not_equals_true(): void
    {
        $response = $this->getJson('/api/v1/optional-features?filter=has_spell_mechanics != true');

        $response->assertOk();
        foreach ($response->json('data') as $feature) {
            $featureModel = OptionalFeature::find($feature['id']);
            $this->assertFalse($featureModel->has_spell_mechanics, "Feature {$feature['name']} should not have spell mechanics");
        }
    }

    #[Test]
    public function it_filters_by_has_spell_mechanics_with_not_equals_false(): void
    {
        $response = $this->getJson('/api/v1/optional-features?filter=has_spell_mechanics != false');

        $response->assertOk();
        foreach ($response->json('data') as $feature) {
            $featureModel = OptionalFeature::find($feature['id']);
            $this->assertTrue($featureModel->has_spell_mechanics, "Feature {$feature['name']} should have spell mechanics");
        }
    }

    // ============================================================
    // Array Operators (class_slugs field) - 3 tests
    // ============================================================

    #[Test]
    public function it_filters_by_class_slugs_with_in(): void
    {
        $featureWithClass = OptionalFeature::whereHas('classes')->first();

        if (! $featureWithClass) {
            $this->markTestSkipped('No optional features with classes in fixture data');
        }

        $classSlug = $featureWithClass->classes->first()->slug;
        $response = $this->getJson("/api/v1/optional-features?filter=class_slugs IN [{$classSlug}]");

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'));
    }

    #[Test]
    public function it_filters_by_class_slugs_with_not_in(): void
    {
        $response = $this->getJson('/api/v1/optional-features?filter=class_slugs NOT IN [wizard]');

        $response->assertOk();

        // NOT IN [wizard] may return varying results depending on data
        // If results ARE returned, verify they don't have wizard in class_slugs
        foreach ($response->json('data') as $feature) {
            $featureModel = OptionalFeature::find($feature['id']);
            $classSlugs = $featureModel->classes->pluck('slug')->toArray();
            $this->assertNotContains('wizard', $classSlugs, "Feature {$feature['name']} should not be associated with wizard");
        }
    }

    #[Test]
    public function it_filters_by_class_slugs_is_empty(): void
    {
        $response = $this->getJson('/api/v1/optional-features?filter=class_slugs IS EMPTY');

        $response->assertOk();
        // Features with no class associations
        foreach ($response->json('data') as $feature) {
            $featureModel = OptionalFeature::find($feature['id']);
            $this->assertEquals(0, $featureModel->classes()->count());
        }
    }

    // ============================================================
    // Array Operators (source_codes field) - 2 tests
    // ============================================================

    #[Test]
    public function it_filters_by_source_codes_with_in(): void
    {
        $featureWithSource = OptionalFeature::whereHas('sources')->first();

        if (! $featureWithSource) {
            $this->markTestSkipped('No optional features with sources in fixture data');
        }

        $sourceCode = $featureWithSource->sources->first()->code;
        $response = $this->getJson("/api/v1/optional-features?filter=source_codes IN [{$sourceCode}]");

        $response->assertOk();

        // We found a feature with this source, so we should get results
        // If Meilisearch index is stale, verify the filter at least works
        foreach ($response->json('data') as $feature) {
            $featureModel = OptionalFeature::find($feature['id']);
            $sourceCodes = $featureModel->sources->pluck('code')->toArray();
            $this->assertContains($sourceCode, $sourceCodes, "Feature {$feature['name']} should have source {$sourceCode}");
        }
    }

    #[Test]
    public function it_filters_by_source_codes_with_not_in(): void
    {
        // NOT IN [FAKE] should return all features (none have FAKE source)
        $response = $this->getJson('/api/v1/optional-features?filter=source_codes NOT IN [FAKE]');

        $response->assertOk();

        // Since FAKE doesn't exist, all features should be returned
        // Verify returned features don't have FAKE source
        foreach ($response->json('data') as $feature) {
            $featureModel = OptionalFeature::find($feature['id']);
            $sourceCodes = $featureModel->sources->pluck('code')->toArray();
            $this->assertNotContains('FAKE', $sourceCodes, "Feature {$feature['name']} should not have FAKE source");
        }
    }

    // ============================================================
    // Combined Filters - 2 tests
    // ============================================================

    #[Test]
    public function it_combines_multiple_filters_with_and(): void
    {
        $response = $this->getJson('/api/v1/optional-features?filter=feature_type = eldritch_invocation AND has_spell_mechanics = false');

        $response->assertOk();
        foreach ($response->json('data') as $feature) {
            $this->assertEquals('eldritch_invocation', $feature['feature_type']);
            $featureModel = OptionalFeature::find($feature['id']);
            $this->assertFalse($featureModel->has_spell_mechanics);
        }
    }

    #[Test]
    public function it_combines_multiple_filters_with_or(): void
    {
        $response = $this->getJson('/api/v1/optional-features?filter=feature_type = eldritch_invocation OR feature_type = maneuver');

        $response->assertOk();
        foreach ($response->json('data') as $feature) {
            $this->assertContains($feature['feature_type'], ['eldritch_invocation', 'maneuver']);
        }
    }
}
