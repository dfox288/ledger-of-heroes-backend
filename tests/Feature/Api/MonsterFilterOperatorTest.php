<?php

namespace Tests\Feature\Api;

use App\Models\Monster;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    use RefreshDatabase;

    protected $seeder = \Database\Seeders\TestDatabaseSeeder::class;

    // ============================================================
    // Integer Operators (challenge_rating field) - 7 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_challenge_rating_with_equals(): void
    {
        $response = $this->getJson('/api/v1/monsters?filter=challenge_rating = 5');

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find CR 5 monsters');

        // Verify all returned monsters have CR 5
        foreach ($response->json('data') as $monster) {
            $this->assertEquals('5', $monster['challenge_rating']);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_challenge_rating_with_not_equals(): void
    {
        $response = $this->getJson('/api/v1/monsters?filter=challenge_rating != 5');

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find non-CR 5 monsters');

        // Verify no returned monsters have CR 5
        foreach ($response->json('data') as $monster) {
            $this->assertNotEquals('5', $monster['challenge_rating']);
        }
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
        // Use a monster that exists in fixtures
        $monster = Monster::first();
        $this->assertNotNull($monster, 'Should have monsters in fixtures');

        $response = $this->getJson("/api/v1/monsters?filter=slug = \"{$monster->slug}\"");

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, $response->json('meta.total'));
        $this->assertEquals($monster->name, $response->json('data.0.name'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_slug_with_not_equals(): void
    {
        $monster = Monster::first();
        $this->assertNotNull($monster, 'Should have monsters in fixtures');

        $response = $this->getJson("/api/v1/monsters?filter=slug != \"{$monster->slug}\"");

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'));

        // Verify the excluded monster is not in results
        $slugs = collect($response->json('data'))->pluck('slug')->toArray();
        $this->assertNotContains($monster->slug, $slugs);
    }

    // ============================================================
    // Boolean Operators (has_legendary_actions field) - 4 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_has_legendary_actions_with_equals_true(): void
    {
        $response = $this->getJson('/api/v1/monsters?filter=has_legendary_actions = true');

        $response->assertOk();

        // Verify all returned monsters are legendary (if any returned)
        foreach ($response->json('data') as $monster) {
            $this->assertTrue($monster['is_legendary'], "Monster {$monster['name']} should be legendary");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_has_legendary_actions_with_equals_false(): void
    {
        $response = $this->getJson('/api/v1/monsters?filter=has_legendary_actions = false');

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find non-legendary monsters');

        // Verify all returned monsters are NOT legendary
        foreach ($response->json('data') as $monster) {
            $this->assertFalse($monster['is_legendary'], "Monster {$monster['name']} should not be legendary");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_has_legendary_actions_with_not_equals_true(): void
    {
        $response = $this->getJson('/api/v1/monsters?filter=has_legendary_actions != true');

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'));

        // Verify all returned monsters are NOT legendary
        foreach ($response->json('data') as $monster) {
            $this->assertFalse($monster['is_legendary'], "Monster {$monster['name']} should not be legendary");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_has_legendary_actions_with_not_equals_false(): void
    {
        $response = $this->getJson('/api/v1/monsters?filter=has_legendary_actions != false');

        $response->assertOk();

        // Verify all returned monsters are legendary (if any returned)
        foreach ($response->json('data') as $monster) {
            $this->assertTrue($monster['is_legendary'], "Monster {$monster['name']} should be legendary");
        }
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

        // Filter for monsters from that source
        $response = $this->getJson("/api/v1/monsters?filter=source_codes IN [{$sourceCode}]&per_page=100");

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), "Should find monsters with source {$sourceCode}");

        // Verify all returned monsters have the source
        foreach ($response->json('data') as $monster) {
            $sourceCodes = collect($monster['sources'] ?? [])->pluck('code')->toArray();
            $this->assertContains($sourceCode, $sourceCodes, "Monster {$monster['name']} should have source {$sourceCode}");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_source_codes_with_not_in(): void
    {
        $monstersWithSources = Monster::has('sources')->count();

        if ($monstersWithSources === 0) {
            $this->markTestSkipped('No monsters have source associations in imported data');
        }

        // Get any source that exists
        $firstMonsterWithSource = Monster::has('sources')->first();
        $sourceCode = $firstMonsterWithSource->sources->first()->source->code;

        $response = $this->getJson("/api/v1/monsters?filter=source_codes NOT IN [{$sourceCode}]");

        $response->assertOk();

        // Verify no returned monsters have that source as their only source
        foreach ($response->json('data') as $monster) {
            $sourceCodes = collect($monster['sources'] ?? [])->pluck('code')->toArray();
            if (count($sourceCodes) === 1) {
                $this->assertNotContains($sourceCode, $sourceCodes, "Monster {$monster['name']} should not have only source {$sourceCode}");
            }
        }
    }
}
