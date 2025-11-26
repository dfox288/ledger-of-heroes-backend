<?php

namespace Tests\Feature\Api;

use App\Models\Background;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WaitsForMeilisearch;
use Tests\TestCase;

class BackgroundFilterOperatorTest extends TestCase
{
    use RefreshDatabase;
    use WaitsForMeilisearch;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Import real backgrounds from PHB for testing (provides realistic data)
        $this->artisan('import:backgrounds', ['file' => 'import-files/backgrounds-phb.xml']);

        // Configure Meilisearch indexes for testing
        $this->artisan('search:configure-indexes');

        // Wait for initial import to be indexed
        $this->waitForMeilisearchIndex('test_backgrounds');
    }

    // ============================================================
    // Integer Operators (id field) - 7 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_id_with_equals(): void
    {
        // Arrange: Verify database has backgrounds
        $this->assertGreaterThan(0, Background::count(), 'Database must be seeded with backgrounds');

        // Get a background to test with
        $background = Background::where('name', 'Acolyte')->first();
        $this->assertNotNull($background, 'Acolyte background should exist in PHB');

        // Act: Filter by specific ID
        $response = $this->getJson("/api/v1/backgrounds?filter=id = {$background->id}");

        // Assert
        $response->assertOk();
        $this->assertEquals(1, $response->json('meta.total'), 'Should find exactly one background');
        $this->assertEquals($background->id, $response->json('data.0.id'), 'Should return the correct background ID');
        $this->assertEquals('Acolyte', $response->json('data.0.name'), 'Should return Acolyte');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_id_with_not_equals(): void
    {
        // Arrange: Verify database has backgrounds
        $this->assertGreaterThan(0, Background::count(), 'Database must be seeded with backgrounds');

        $acolyte = Background::where('name', 'Acolyte')->first();
        $this->assertNotNull($acolyte, 'Acolyte background should exist');

        // Act: Filter by id != acolyte
        $response = $this->getJson("/api/v1/backgrounds?filter=id != {$acolyte->id}");

        // Assert: Should return all backgrounds except Acolyte
        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find non-Acolyte backgrounds');

        // Verify Acolyte is not in results
        foreach ($response->json('data') as $background) {
            $this->assertNotEquals($acolyte->id, $background['id'], "Background {$background['name']} ID should not be {$acolyte->id}");
        }

        // Verify we have other backgrounds
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertNotContains('Acolyte', $names, 'Acolyte should be excluded');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_id_with_greater_than(): void
    {
        // Arrange: Get backgrounds and find a mid-range ID
        $this->assertGreaterThan(0, Background::count(), 'Database must be seeded with backgrounds');

        $backgrounds = Background::orderBy('id')->get();
        $midIndex = (int) floor($backgrounds->count() / 2);
        $midBackground = $backgrounds[$midIndex];

        // Act: Filter by id > mid-range
        $response = $this->getJson("/api/v1/backgrounds?filter=id > {$midBackground->id}");

        // Assert: Should return only backgrounds with ID > midBackground
        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find backgrounds with ID > mid-range');

        // Verify all returned backgrounds have ID > midBackground
        foreach ($response->json('data') as $background) {
            $this->assertGreaterThan($midBackground->id, $background['id'], "Background {$background['name']} ID should be > {$midBackground->id}");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_id_with_greater_than_or_equal(): void
    {
        // Arrange: Get a specific background
        $this->assertGreaterThan(0, Background::count(), 'Database must be seeded with backgrounds');

        $backgrounds = Background::orderBy('id')->get();
        $midIndex = (int) floor($backgrounds->count() / 2);
        $midBackground = $backgrounds[$midIndex];

        // Act: Filter by id >= mid-range (include per_page=100 to get all results on one page)
        $response = $this->getJson("/api/v1/backgrounds?filter=id >= {$midBackground->id}&per_page=100");

        // Assert: Should include midBackground and all higher IDs
        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find backgrounds with ID >= mid-range');

        // Verify all returned backgrounds have ID >= midBackground
        foreach ($response->json('data') as $background) {
            $this->assertGreaterThanOrEqual($midBackground->id, $background['id'], "Background {$background['name']} ID should be >= {$midBackground->id}");
        }

        // Verify midBackground is included
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($midBackground->id, $ids, 'Mid-range background should be included (>= is inclusive)');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_id_with_less_than(): void
    {
        // Arrange: Get backgrounds and find a mid-range ID
        $this->assertGreaterThan(0, Background::count(), 'Database must be seeded with backgrounds');

        $backgrounds = Background::orderBy('id')->get();
        $midIndex = (int) floor($backgrounds->count() / 2);
        $midBackground = $backgrounds[$midIndex];

        // Act: Filter by id < mid-range
        $response = $this->getJson("/api/v1/backgrounds?filter=id < {$midBackground->id}");

        // Assert: Should return only backgrounds with ID < midBackground
        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find backgrounds with ID < mid-range');

        // Verify all returned backgrounds have ID < midBackground
        foreach ($response->json('data') as $background) {
            $this->assertLessThan($midBackground->id, $background['id'], "Background {$background['name']} ID should be < {$midBackground->id}");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_id_with_less_than_or_equal(): void
    {
        // Arrange: Get a specific background
        $this->assertGreaterThan(0, Background::count(), 'Database must be seeded with backgrounds');

        $backgrounds = Background::orderBy('id')->get();
        $midIndex = (int) floor($backgrounds->count() / 2);
        $midBackground = $backgrounds[$midIndex];

        // Act: Filter by id <= mid-range (include per_page=100 to get all results on one page)
        $response = $this->getJson("/api/v1/backgrounds?filter=id <= {$midBackground->id}&per_page=100");

        // Assert: Should include midBackground and all lower IDs
        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find backgrounds with ID <= mid-range');

        // Verify all returned backgrounds have ID <= midBackground
        foreach ($response->json('data') as $background) {
            $this->assertLessThanOrEqual($midBackground->id, $background['id'], "Background {$background['name']} ID should be <= {$midBackground->id}");
        }

        // Verify midBackground is included
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($midBackground->id, $ids, 'Mid-range background should be included (<= is inclusive)');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_id_with_to_range(): void
    {
        // Arrange: Get backgrounds and define a range
        $this->assertGreaterThan(0, Background::count(), 'Database must be seeded with backgrounds');

        $backgrounds = Background::orderBy('id')->get();
        $this->assertGreaterThanOrEqual(3, $backgrounds->count(), 'Need at least 3 backgrounds for range test');

        $firstBg = $backgrounds[0];
        $lastBg = $backgrounds[2];

        // Act: Filter by id range (TO operator is inclusive) - add per_page to get all on one page
        $response = $this->getJson("/api/v1/backgrounds?filter=id {$firstBg->id} TO {$lastBg->id}&per_page=100");

        // Assert: Should include all backgrounds in range
        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find backgrounds in ID range');

        // Verify all returned backgrounds are in range
        foreach ($response->json('data') as $background) {
            $this->assertGreaterThanOrEqual($firstBg->id, $background['id'], "Background {$background['name']} ID should be >= {$firstBg->id}");
            $this->assertLessThanOrEqual($lastBg->id, $background['id'], "Background {$background['name']} ID should be <= {$lastBg->id}");
        }

        // Edge case test: Verify TO range is inclusive (both endpoints should be included)
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($firstBg->id, $ids, 'First ID should be included (TO is inclusive)');
        $this->assertContains($lastBg->id, $ids, 'Last ID should be included (TO is inclusive)');
    }

    // ============================================================
    // String Operators (slug field) - 2 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_slug_with_equals(): void
    {
        // Arrange: Verify database has backgrounds
        $this->assertGreaterThan(0, Background::count(), 'Database must be seeded with backgrounds');

        // Act: Filter by slug = "acolyte" (well-known PHB background)
        // Note: Filtering uses Meilisearch field name (slug)
        $response = $this->getJson('/api/v1/backgrounds?filter=slug = "acolyte"');

        // Assert
        $response->assertOk();
        $this->assertEquals(1, $response->json('meta.total'), 'Should find exactly one acolyte background');

        // Verify the returned background has slug = 'acolyte'
        $this->assertEquals('acolyte', $response->json('data.0.slug'), 'Should return acolyte background');
        $this->assertEquals('Acolyte', $response->json('data.0.name'), 'Should return Acolyte background');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_slug_with_not_equals(): void
    {
        // Arrange: Verify database has backgrounds
        $this->assertGreaterThan(0, Background::count(), 'Database must be seeded with backgrounds');

        // Act: Filter by slug != "acolyte"
        // Note: Filtering uses Meilisearch field name (slug)
        $response = $this->getJson('/api/v1/backgrounds?filter=slug != "acolyte"');

        // Assert: Should return all backgrounds except acolyte
        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find non-acolyte backgrounds');

        // Verify no acolyte in results
        foreach ($response->json('data') as $background) {
            $this->assertNotEquals('acolyte', $background['slug'], 'Background slug should not be acolyte');
        }

        // Verify we have other backgrounds
        $slugs = collect($response->json('data'))->pluck('slug')->toArray();
        $this->assertNotContains('acolyte', $slugs, 'Acolyte should be excluded');
        $this->assertGreaterThan(1, count($slugs), 'Should have multiple non-acolyte backgrounds');
    }

    // ============================================================
    // Array Operators (skill_proficiencies field) - 2 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_skill_proficiencies_with_in(): void
    {
        // Arrange: Create backgrounds with known skill proficiencies using factories
        // This avoids relying on stale Meilisearch test index data
        $insightSkill = \App\Models\Skill::where('slug', 'insight')->first();
        $religionSkill = \App\Models\Skill::where('slug', 'religion')->first();
        $athleticsSkill = \App\Models\Skill::where('slug', 'athletics')->first();

        $this->assertNotNull($insightSkill, 'Insight skill must exist');
        $this->assertNotNull($religionSkill, 'Religion skill must exist');
        $this->assertNotNull($athleticsSkill, 'Athletics skill must exist');

        // Create a background with insight proficiency
        $bgWithInsight = Background::factory()->create(['name' => 'Scholar', 'slug' => 'scholar']);
        \App\Models\Proficiency::factory()
            ->forEntity(Background::class, $bgWithInsight->id)
            ->create(['proficiency_type' => 'skill', 'skill_id' => $insightSkill->id]);

        // Create a background with religion proficiency
        $bgWithReligion = Background::factory()->create(['name' => 'Priest', 'slug' => 'priest']);
        \App\Models\Proficiency::factory()
            ->forEntity(Background::class, $bgWithReligion->id)
            ->create(['proficiency_type' => 'skill', 'skill_id' => $religionSkill->id]);

        // Create a background without insight or religion (only athletics)
        $bgWithAthletics = Background::factory()->create(['name' => 'Athlete', 'slug' => 'athlete']);
        \App\Models\Proficiency::factory()
            ->forEntity(Background::class, $bgWithAthletics->id)
            ->create(['proficiency_type' => 'skill', 'skill_id' => $athleticsSkill->id]);

        // Reload models to include proficiency relationships for toSearchableArray()
        $bgWithInsight->refresh()->load('proficiencies.skill');
        $bgWithReligion->refresh()->load('proficiencies.skill');
        $bgWithAthletics->refresh()->load('proficiencies.skill');

        // Re-index these specific backgrounds and wait for Meilisearch
        $bgWithInsight->searchable();
        $bgWithReligion->searchable();
        $bgWithAthletics->searchable();
        $this->waitForMeilisearchModels([$bgWithInsight, $bgWithReligion, $bgWithAthletics]);

        // Act: Filter by skill_proficiencies IN [insight, religion]
        $response = $this->getJson('/api/v1/backgrounds?filter=skill_proficiencies IN [insight, religion]&per_page=100');

        // Assert: Should return backgrounds that grant insight OR religion proficiency
        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find backgrounds with insight or religion proficiency');

        // Verify Scholar and Priest are included
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Scholar', $names, 'Scholar grants insight proficiency');
        $this->assertContains('Priest', $names, 'Priest grants religion proficiency');
        $this->assertNotContains('Athlete', $names, 'Athlete does not grant insight or religion');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_skill_proficiencies_with_not_in(): void
    {
        // Arrange: Create backgrounds with known skill proficiencies using factories
        $insightSkill = \App\Models\Skill::where('slug', 'insight')->first();
        $athleticsSkill = \App\Models\Skill::where('slug', 'athletics')->first();

        $this->assertNotNull($insightSkill, 'Insight skill must exist');
        $this->assertNotNull($athleticsSkill, 'Athletics skill must exist');

        // Create a background with insight proficiency
        $bgWithInsight = Background::factory()->create(['name' => 'Mystic', 'slug' => 'mystic']);
        \App\Models\Proficiency::factory()
            ->forEntity(Background::class, $bgWithInsight->id)
            ->create(['proficiency_type' => 'skill', 'skill_id' => $insightSkill->id]);

        // Create a background without insight (only athletics)
        $bgWithoutInsight = Background::factory()->create(['name' => 'Warrior', 'slug' => 'warrior']);
        \App\Models\Proficiency::factory()
            ->forEntity(Background::class, $bgWithoutInsight->id)
            ->create(['proficiency_type' => 'skill', 'skill_id' => $athleticsSkill->id]);

        // Reload models to include proficiency relationships for toSearchableArray()
        $bgWithInsight->refresh()->load('proficiencies.skill');
        $bgWithoutInsight->refresh()->load('proficiencies.skill');

        // Re-index these specific backgrounds and wait for Meilisearch
        $bgWithInsight->searchable();
        $bgWithoutInsight->searchable();
        $this->waitForMeilisearchModels([$bgWithInsight, $bgWithoutInsight]);

        // Act: Filter by skill_proficiencies NOT IN [insight]
        $response = $this->getJson('/api/v1/backgrounds?filter=skill_proficiencies NOT IN [insight]&per_page=100');

        // Assert: Should return backgrounds that do NOT grant insight proficiency
        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find backgrounds without insight proficiency');

        // Verify Mystic is excluded (grants insight)
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertNotContains('Mystic', $names, 'Mystic grants insight proficiency (should be excluded)');
        $this->assertContains('Warrior', $names, 'Warrior does not grant insight (should be included)');
    }
}
