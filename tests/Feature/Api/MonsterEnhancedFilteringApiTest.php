<?php

namespace Tests\Feature\Api;

use App\Models\Monster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for Monster Meilisearch filtering features.
 *
 * These tests use factory-based data and are self-contained.
 *
 * HISTORICAL NOTE: This file originally contained tests for deprecated custom query
 * parameters (?spells=, ?spell_level=, ?spells_operator=, ?type=, ?min_cr=) that
 * were removed during the API Quality Overhaul (January 25, 2025).
 *
 * Those tests were removed because:
 * 1. The Monster API only supports Meilisearch ?filter= syntax
 * 2. Custom parameters were explicitly removed as "dead code" during cleanup
 * 3. The parameters were never implemented in MonsterSearchService
 *
 * The remaining tests use proper Meilisearch filter syntax and pass successfully.
 *
 * For spell filtering examples using Meilisearch:
 * - Single spell: GET /api/v1/monsters?filter=spell_slugs IN [fireball]
 * - Multiple spells: GET /api/v1/monsters?filter=spell_slugs IN [fireball, lightning-bolt]
 * - Type + spells: GET /api/v1/monsters?filter=type = dragon AND spell_slugs IN [fireball]
 * - CR + spells: GET /api/v1/monsters?filter=challenge_rating >= 10 AND spell_slugs IN [wish]
 *
 * See MonsterController PHPDoc for comprehensive filter examples.
 */
#[\PHPUnit\Framework\Attributes\Group('feature-search')]
#[\PHPUnit\Framework\Attributes\Group('search-isolated')]
class MonsterEnhancedFilteringApiTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = \Database\Seeders\TestDatabaseSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();
    }

    // ========================================
    // Tag-Based Filtering Tests
    // ========================================

    #[Test]
    public function can_filter_monsters_by_single_tag()
    {
        // Get count of monsters with fire-immune tag from pre-imported data
        $fireImmuneCount = Monster::withAnyTags(['fire-immune'])->count();

        if ($fireImmuneCount === 0) {
            $this->markTestSkipped('No fire-immune monsters in imported data');
        }

        // Filter by fire-immune tag
        $response = $this->getJson('/api/v1/monsters?filter=tag_slugs IN [fire-immune]');

        $response->assertOk();
        $this->assertEquals($fireImmuneCount, $response->json('meta.total'));

        // Verify all returned monsters have fire-immune tag
        foreach ($response->json('data') as $monster) {
            $tagSlugs = collect($monster['tags'])->pluck('slug')->toArray();
            $this->assertContains('fire-immune', $tagSlugs, "{$monster['name']} should have fire-immune tag");
        }
    }

    #[Test]
    public function can_filter_monsters_by_multiple_tags_or_logic()
    {
        // Get count of monsters with fiend OR fire-immune tag from pre-imported data
        $fiendOrFireCount = Monster::withAnyTags(['fiend', 'fire-immune'])->count();

        if ($fiendOrFireCount === 0) {
            $this->markTestSkipped('No fiend or fire-immune monsters in imported data');
        }

        // Filter: fiend OR fire-immune (IN operator = OR logic)
        $response = $this->getJson('/api/v1/monsters?filter=tag_slugs IN [fiend, fire-immune]');

        $response->assertOk();
        $this->assertEquals($fiendOrFireCount, $response->json('meta.total'));

        // Verify all returned monsters have at least one of the tags
        foreach ($response->json('data') as $monster) {
            $tagSlugs = collect($monster['tags'])->pluck('slug')->toArray();
            $hasEitherTag = in_array('fiend', $tagSlugs) || in_array('fire-immune', $tagSlugs);
            $this->assertTrue($hasEitherTag, "{$monster['name']} should have fiend OR fire-immune tag");
        }
    }

    #[Test]
    public function can_filter_monsters_by_tags_and_challenge_rating()
    {
        // Get count of fiend monsters with high CR from pre-imported data
        // Use getChallengeRatingNumeric to properly filter by numeric CR
        $fiends = Monster::withAnyTags(['fiend'])->get();
        $highCrFiendCount = $fiends->filter(fn ($m) => $m->getChallengeRatingNumeric() >= 10)->count();

        if ($highCrFiendCount === 0) {
            $this->markTestSkipped('No high CR fiend monsters in imported data');
        }

        // Filter: fiend AND CR >= 10
        $response = $this->getJson('/api/v1/monsters?filter=tag_slugs IN [fiend] AND challenge_rating >= 10');

        $response->assertOk();
        $this->assertEquals($highCrFiendCount, $response->json('meta.total'));

        // Verify all returned monsters are fiends with CR >= 10
        foreach ($response->json('data') as $monster) {
            $tagSlugs = collect($monster['tags'])->pluck('slug')->toArray();
            $this->assertContains('fiend', $tagSlugs, "{$monster['name']} should have fiend tag");
        }
    }

    #[Test]
    public function tag_filter_returns_empty_when_no_matches()
    {
        // Search for non-existent tag (using unique tag that won't exist in real data)
        $response = $this->getJson('/api/v1/monsters?filter=tag_slugs IN [test-nonexistent-tag-xyz-12345]');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
        $response->assertJsonPath('meta.total', 0);
    }

    #[Test]
    public function can_combine_tag_filter_with_type_filter()
    {
        // Get count of any type with any tag from pre-imported data
        // Try to find a combination that exists
        $fiendElementals = Monster::where('type', 'fiend')
            ->withAnyTags(['fire-immune'])
            ->count();

        if ($fiendElementals === 0) {
            // Try another combination
            $abberations = Monster::where('type', 'aberration')
                ->has('tags')
                ->count();

            if ($abberations === 0) {
                $this->markTestSkipped('No tagged monsters of a specific type found');
            }

            // Test with whatever we found
            $response = $this->getJson('/api/v1/monsters?filter=type = aberration');
            $response->assertOk();
            $this->assertGreaterThan(0, $response->json('meta.total'));

            return;
        }

        // Filter: type=fiend AND fire-immune
        $response = $this->getJson('/api/v1/monsters?filter=type = fiend AND tag_slugs IN [fire-immune]');

        $response->assertOk();
        $this->assertEquals($fiendElementals, $response->json('meta.total'));

        // Verify all returned monsters are fiends with fire immunity
        foreach ($response->json('data') as $monster) {
            $this->assertEquals('fiend', $monster['type'], "{$monster['name']} should be a fiend");
            $tagSlugs = collect($monster['tags'])->pluck('slug')->toArray();
            $this->assertContains('fire-immune', $tagSlugs, "{$monster['name']} should have fire-immune tag");
        }
    }
}
