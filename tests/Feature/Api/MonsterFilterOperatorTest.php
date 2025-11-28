<?php

namespace Tests\Feature\Api;

use App\Models\Monster;
use Tests\TestCase;

/**
 * Tests for Monster filter operators using Meilisearch.
 *
 * These tests use factory-based data and are self-contained.
 */
#[\PHPUnit\Framework\Attributes\Group('feature-search')]
#[\PHPUnit\Framework\Attributes\Group('search-isolated')]
class MonsterFilterOperatorTest extends TestCase
{
    protected $seed = false;

    // ============================================================
    // Integer Operators (challenge_rating field) - 7 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_challenge_rating_with_equals(): void
    {
        $cr5Count = Monster::where('challenge_rating', '5')->count();
        $this->assertGreaterThan(0, $cr5Count, 'Should have CR 5 monsters in imported data');

        $response = $this->getJson('/api/v1/monsters?filter=challenge_rating = 5');

        $response->assertOk();
        $this->assertEquals($cr5Count, $response->json('meta.total'), 'Should find all CR 5 monsters');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_challenge_rating_with_not_equals(): void
    {
        $nonCr5Count = Monster::where('challenge_rating', '!=', '5')->count();

        $response = $this->getJson('/api/v1/monsters?filter=challenge_rating != 5');

        $response->assertOk();
        $this->assertEquals($nonCr5Count, $response->json('meta.total'), 'Should exclude CR 5 monsters');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_challenge_rating_with_greater_than(): void
    {
        $response = $this->getJson('/api/v1/monsters?filter=challenge_rating > 20');

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find high CR monsters');

        // Verify all returned monsters have CR > 20
        foreach ($response->json('data') as $monster) {
            $this->assertGreaterThan(20, (int) $monster['challenge_rating']);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_challenge_rating_with_greater_than_or_equal(): void
    {
        $response = $this->getJson('/api/v1/monsters?filter=challenge_rating >= 20&per_page=100');

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'));

        // Verify all returned monsters have CR >= 20
        foreach ($response->json('data') as $monster) {
            $this->assertGreaterThanOrEqual(20, (int) $monster['challenge_rating']);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_challenge_rating_with_less_than(): void
    {
        $response = $this->getJson('/api/v1/monsters?filter=challenge_rating < 2');

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find low CR monsters');

        // Verify all returned monsters have CR < 2
        foreach ($response->json('data') as $monster) {
            $this->assertLessThan(2, (float) $monster['challenge_rating']);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_challenge_rating_with_less_than_or_equal(): void
    {
        $response = $this->getJson('/api/v1/monsters?filter=challenge_rating <= 1&per_page=100');

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'));

        // Verify all returned monsters have CR <= 1
        foreach ($response->json('data') as $monster) {
            $this->assertLessThanOrEqual(1, (float) $monster['challenge_rating']);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_challenge_rating_with_to_range(): void
    {
        $response = $this->getJson('/api/v1/monsters?filter=challenge_rating 5 TO 10&per_page=100');

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'));

        // Verify all returned monsters have CR between 5 and 10 (inclusive)
        foreach ($response->json('data') as $monster) {
            $cr = (int) $monster['challenge_rating'];
            $this->assertGreaterThanOrEqual(5, $cr);
            $this->assertLessThanOrEqual(10, $cr);
        }
    }

    // ============================================================
    // String Operators (slug field) - 2 tests
    // Note: 'name' is not filterable, use 'slug' instead
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_slug_with_equals(): void
    {
        // Use a known monster from MM
        $response = $this->getJson('/api/v1/monsters?filter=slug = "goblin"');

        $response->assertOk();
        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals('Goblin', $response->json('data.0.name'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_slug_with_not_equals(): void
    {
        $totalMonsters = Monster::count();

        $response = $this->getJson('/api/v1/monsters?filter=slug != "goblin"');

        $response->assertOk();
        $this->assertEquals($totalMonsters - 1, $response->json('meta.total'));
    }

    // ============================================================
    // Boolean Operators (has_legendary_actions field) - 4 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_has_legendary_actions_with_equals_true(): void
    {
        // has_legendary_actions is computed from legendaryActions relationship (non-lair only)
        $legendaryCount = Monster::whereHas('legendaryActions', fn ($q) => $q->where('is_lair_action', false))->count();

        $response = $this->getJson('/api/v1/monsters?filter=has_legendary_actions = true');

        $response->assertOk();
        $this->assertEquals($legendaryCount, $response->json('meta.total'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_has_legendary_actions_with_equals_false(): void
    {
        $nonLegendaryCount = Monster::whereDoesntHave('legendaryActions', fn ($q) => $q->where('is_lair_action', false))->count();

        $response = $this->getJson('/api/v1/monsters?filter=has_legendary_actions = false');

        $response->assertOk();
        $this->assertEquals($nonLegendaryCount, $response->json('meta.total'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_has_legendary_actions_with_not_equals_true(): void
    {
        $nonLegendaryCount = Monster::whereDoesntHave('legendaryActions', fn ($q) => $q->where('is_lair_action', false))->count();

        $response = $this->getJson('/api/v1/monsters?filter=has_legendary_actions != true');

        $response->assertOk();
        $this->assertEquals($nonLegendaryCount, $response->json('meta.total'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_has_legendary_actions_with_not_equals_false(): void
    {
        $legendaryCount = Monster::whereHas('legendaryActions', fn ($q) => $q->where('is_lair_action', false))->count();

        $response = $this->getJson('/api/v1/monsters?filter=has_legendary_actions != false');

        $response->assertOk();
        $this->assertEquals($legendaryCount, $response->json('meta.total'));
    }

    // ============================================================
    // Array Operators (source_codes field) - 2 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_source_codes_with_in(): void
    {
        // Count monsters with any sources via relationship
        $monstersWithSources = Monster::has('sources')->count();

        if ($monstersWithSources === 0) {
            $this->markTestSkipped('No monsters have source associations in imported data');
        }

        // Get the first available source code
        $firstMonsterWithSource = Monster::has('sources')->first();
        $sourceCode = $firstMonsterWithSource->sources->first()->source->code;

        // Count monsters with that source
        $sourceCount = Monster::whereHas('sources', fn ($q) => $q->whereHas('source', fn ($sq) => $sq->where('code', $sourceCode)))->count();

        // Filter for monsters from that source
        $response = $this->getJson("/api/v1/monsters?filter=source_codes IN [{$sourceCode}]&per_page=100");

        $response->assertOk();
        $this->assertEquals($sourceCount, $response->json('meta.total'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_source_codes_with_not_in(): void
    {
        $monstersWithSources = Monster::has('sources')->count();

        if ($monstersWithSources === 0) {
            $this->markTestSkipped('No monsters have source associations in imported data');
        }

        $totalMonsters = Monster::count();

        // Get any source that exists
        $firstMonsterWithSource = Monster::has('sources')->first();
        $sourceCode = $firstMonsterWithSource->sources->first()->source->code;

        $sourceMonsters = Monster::whereHas('sources', fn ($q) => $q->whereHas('source', fn ($sq) => $sq->where('code', $sourceCode)))->count();

        $response = $this->getJson("/api/v1/monsters?filter=source_codes NOT IN [{$sourceCode}]");

        $response->assertOk();
        // Should return all non-source monsters
        $this->assertEquals($totalMonsters - $sourceMonsters, $response->json('meta.total'));
    }
}
