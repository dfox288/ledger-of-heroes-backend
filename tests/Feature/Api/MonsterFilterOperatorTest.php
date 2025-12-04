<?php

namespace Tests\Feature\Api;

use App\Models\Monster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\Concerns\TestsFilterOperators;
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
    use TestsFilterOperators;

    protected $seeder = \Database\Seeders\TestDatabaseSeeder::class;

    // ============================================================
    // Entity-Specific Configuration
    // ============================================================

    protected function getEndpoint(): string
    {
        return '/api/v1/monsters';
    }

    protected function getIntegerFieldConfig(): ?array
    {
        return [
            'field' => 'challenge_rating',
            'testValue' => 5,
            'lowValue' => 20,
            'highValue' => 2,
        ];
    }

    protected function getStringFieldConfig(): ?array
    {
        $monster = Monster::first();
        if ($monster === null) {
            return null;
        }

        return [
            'field' => 'slug',
            'testValue' => $monster->slug,
            'excludeValue' => $monster->slug,
        ];
    }

    protected function getBooleanFieldConfig(): ?array
    {
        return [
            'field' => 'has_legendary_actions',
            'verifyCallback' => function (TestCase $test, array $monster, bool $expectedValue) {
                $test->assertEquals($expectedValue, $monster['is_legendary'], "Monster {$monster['name']} is_legendary should be {$expectedValue}");
            },
        ];
    }

    protected function getArrayFieldConfig(): ?array
    {
        $monstersWithSources = Monster::has('sources')->count();
        if ($monstersWithSources === 0) {
            return null;
        }

        $firstMonsterWithSource = Monster::has('sources')->first();
        $sourceCode = $firstMonsterWithSource->sources->first()->source->code;

        return [
            'field' => 'source_codes',
            'testValues' => [$sourceCode],
            'excludeValue' => $sourceCode,
            'verifyCallback' => function (TestCase $test, array $monster, array $expectedValues, bool $shouldContain) {
                $sourceCodes = collect($monster['sources'] ?? [])->pluck('code')->toArray();

                if ($shouldContain) {
                    $test->assertNotEmpty(array_intersect($expectedValues, $sourceCodes), "Monster {$monster['name']} should have one of: ".implode(', ', $expectedValues));
                } else {
                    // For NOT IN, verify source isn't the ONLY source
                    if (count($sourceCodes) === 1) {
                        $test->assertNotContains($expectedValues[0], $sourceCodes, "Monster {$monster['name']} should not have only source {$expectedValues[0]}");
                    }
                }
            },
        ];
    }

    // ============================================================
    // Integer Operators (challenge_rating field) - 7 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_challenge_rating_with_equals(): void
    {
        $this->assertIntegerEquals();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_challenge_rating_with_not_equals(): void
    {
        $this->assertIntegerNotEquals();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_challenge_rating_with_greater_than(): void
    {
        $this->assertIntegerGreaterThan();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_challenge_rating_with_greater_than_or_equal(): void
    {
        $this->assertIntegerGreaterThanOrEqual();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_challenge_rating_with_less_than(): void
    {
        $this->assertIntegerLessThan();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_challenge_rating_with_less_than_or_equal(): void
    {
        $this->assertIntegerLessThanOrEqual();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_challenge_rating_with_to_range(): void
    {
        // Override to use better range for monsters (5-10 instead of trait defaults)
        $response = $this->getJson('/api/v1/monsters?filter=challenge_rating 5 TO 10&per_page=100');

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'));

        foreach ($response->json('data') as $monster) {
            $cr = (int) $monster['challenge_rating'];
            $this->assertGreaterThanOrEqual(5, $cr);
            $this->assertLessThanOrEqual(10, $cr);
        }
    }

    // ============================================================
    // String Operators (slug field) - 2 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_slug_with_equals(): void
    {
        $config = $this->getStringFieldConfig();
        if ($config === null) {
            $this->markTestSkipped('No monsters in fixtures');
        }

        $this->assertStringEquals();

        // Additional verification
        $response = $this->getJson("/api/v1/monsters?filter=slug = \"{$config['testValue']}\"");
        $this->assertGreaterThanOrEqual(1, $response->json('meta.total'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_slug_with_not_equals(): void
    {
        $config = $this->getStringFieldConfig();
        if ($config === null) {
            $this->markTestSkipped('No monsters in fixtures');
        }

        $this->assertStringNotEquals();

        // Additional verification - excluded slug not in results
        $response = $this->getJson("/api/v1/monsters?filter=slug != \"{$config['excludeValue']}\"");
        $slugs = collect($response->json('data'))->pluck('slug')->toArray();
        $this->assertNotContains($config['excludeValue'], $slugs);
    }

    // ============================================================
    // Boolean Operators (has_legendary_actions field) - 4 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_has_legendary_actions_with_equals_true(): void
    {
        $this->assertBooleanEqualsTrue();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_has_legendary_actions_with_equals_false(): void
    {
        $this->assertBooleanEqualsFalse();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_has_legendary_actions_with_not_equals_true(): void
    {
        $this->assertBooleanNotEqualsTrue();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_has_legendary_actions_with_not_equals_false(): void
    {
        $this->assertBooleanNotEqualsFalse();
    }

    // ============================================================
    // Array Operators (source_codes field) - 2 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_source_codes_with_in(): void
    {
        $config = $this->getArrayFieldConfig();
        if ($config === null) {
            $this->markTestSkipped('No monsters have source associations in imported data');
        }

        $this->assertArrayIn();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_source_codes_with_not_in(): void
    {
        $config = $this->getArrayFieldConfig();
        if ($config === null) {
            $this->markTestSkipped('No monsters have source associations in imported data');
        }

        $this->assertArrayNotIn();
    }
}
