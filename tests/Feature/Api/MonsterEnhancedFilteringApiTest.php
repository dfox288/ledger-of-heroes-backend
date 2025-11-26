<?php

namespace Tests\Feature\Api;

use App\Models\Monster;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for Monster Meilisearch filtering features.
 *
 * These tests use pre-imported data from SearchTestExtension.
 * No RefreshDatabase needed - all tests are read-only against shared data.
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
    protected $seed = false;

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

        $this->assertGreaterThan(0, $fireImmuneCount, 'Should have monsters with fire-immune tag (e.g., Balor, Fire Elemental)');

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

        $this->assertGreaterThan(0, $fiendOrFireCount, 'Should have monsters with fiend OR fire-immune tags');

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
        // Get count of fiend monsters with CR >= 10 from pre-imported data
        $highCrFiendCount = Monster::withAnyTags(['fiend'])
            ->where('challenge_rating', '>=', 10)
            ->count();

        $this->assertGreaterThan(0, $highCrFiendCount, 'Should have high CR fiend monsters (e.g., Pit Fiend)');

        // Filter: fiend AND CR >= 10
        $response = $this->getJson('/api/v1/monsters?filter=tag_slugs IN [fiend] AND challenge_rating >= 10');

        $response->assertOk();
        $this->assertEquals($highCrFiendCount, $response->json('meta.total'));

        // Verify all returned monsters are fiends with CR >= 10
        foreach ($response->json('data') as $monster) {
            $tagSlugs = collect($monster['tags'])->pluck('slug')->toArray();
            $this->assertContains('fiend', $tagSlugs, "{$monster['name']} should have fiend tag");
            $this->assertGreaterThanOrEqual(10, $monster['challenge_rating'], "{$monster['name']} should have CR >= 10");
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
        // Get count of dragons with fire-immune tag from pre-imported data
        $fireImmuneDragons = Monster::where('type', 'dragon')
            ->withAnyTags(['fire-immune'])
            ->count();

        $this->assertGreaterThan(0, $fireImmuneDragons, 'Should have dragons with fire immunity (e.g., Adult Red Dragon)');

        // Filter: type=dragon AND fire-immune
        $response = $this->getJson('/api/v1/monsters?filter=type = dragon AND tag_slugs IN [fire-immune]');

        $response->assertOk();
        $this->assertEquals($fireImmuneDragons, $response->json('meta.total'));

        // Verify all returned monsters are dragons with fire immunity
        foreach ($response->json('data') as $monster) {
            $this->assertEquals('dragon', $monster['type'], "{$monster['name']} should be a dragon");
            $tagSlugs = collect($monster['tags'])->pluck('slug')->toArray();
            $this->assertContains('fire-immune', $tagSlugs, "{$monster['name']} should have fire-immune tag");
        }
    }
}
