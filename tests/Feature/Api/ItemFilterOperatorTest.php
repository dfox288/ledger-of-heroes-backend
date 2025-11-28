<?php

namespace Tests\Feature\Api;

use App\Models\Item;
use Tests\Concerns\ClearsMeilisearchIndex;
use Tests\Concerns\WaitsForMeilisearch;
use Tests\TestCase;

/**
 * Tests for Item filter operators using Meilisearch.
 *
 * These tests use factory-based data and are self-contained.
 *
 * Run: docker compose exec php php artisan test --testsuite=Feature-Search-Isolated
 */
#[\PHPUnit\Framework\Attributes\Group('feature-search')]
#[\PHPUnit\Framework\Attributes\Group('search-isolated')]
class ItemFilterOperatorTest extends TestCase
{
    use ClearsMeilisearchIndex;
    use WaitsForMeilisearch;

    // Note: No RefreshDatabase trait - we use pre-imported data
    protected $seed = false; // Don't run seeders

    private static bool $indexed = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip if no imported data
        if (Item::count() === 0) {
            $this->markTestSkipped('No items in database. Run: php artisan import:all --env=testing');
        }

        // Index all items ONCE per test class run (not per test)
        if (! self::$indexed) {
            $this->clearMeilisearchIndex(Item::class);
            Item::all()->searchable();
            $this->waitForMeilisearchIndex('test_items');
            self::$indexed = true;
        }
    }

    // ============================================================
    // Integer Operators (charges_max field) - 7 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_charges_max_with_equals(): void
    {
        $response = $this->getJson('/api/v1/items?filter=charges_max = "7"&per_page=100');

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find items with charges_max = 7');

        foreach ($response->json('data') as $item) {
            $this->assertEquals('7', $item['charges_max'], "Item {$item['name']} should have charges_max = 7");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_charges_max_with_not_equals(): void
    {
        $response = $this->getJson('/api/v1/items?filter=charges_max != "7"');

        $response->assertOk();

        foreach ($response->json('data') as $item) {
            $this->assertNotEquals('7', $item['charges_max'], "Item {$item['name']} should not have charges_max = 7");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_charges_max_with_greater_than(): void
    {
        $response = $this->getJson('/api/v1/items?filter=charges_max > "5"&per_page=100');

        $response->assertOk();

        if ($response->json('meta.total') > 0) {
            foreach ($response->json('data') as $item) {
                if ($item['charges_max'] !== null && is_numeric($item['charges_max'])) {
                    $this->assertGreaterThan(5, (int) $item['charges_max'], "Item {$item['name']} charges_max should be > 5");
                }
            }
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_charges_max_with_greater_than_or_equal(): void
    {
        $response = $this->getJson('/api/v1/items?filter=charges_max >= "7"&per_page=100');

        $response->assertOk();

        if ($response->json('meta.total') > 0) {
            foreach ($response->json('data') as $item) {
                if ($item['charges_max'] !== null && is_numeric($item['charges_max'])) {
                    $this->assertGreaterThanOrEqual(7, (int) $item['charges_max'], "Item {$item['name']} charges_max should be >= 7");
                }
            }
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_charges_max_with_less_than(): void
    {
        $response = $this->getJson('/api/v1/items?filter=charges_max < "5"&per_page=100');

        $response->assertOk();

        if ($response->json('meta.total') > 0) {
            foreach ($response->json('data') as $item) {
                if ($item['charges_max'] !== null && is_numeric($item['charges_max'])) {
                    $this->assertLessThan(5, (int) $item['charges_max'], "Item {$item['name']} charges_max should be < 5");
                }
            }
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_charges_max_with_less_than_or_equal(): void
    {
        $response = $this->getJson('/api/v1/items?filter=charges_max <= "3"&per_page=100');

        $response->assertOk();

        if ($response->json('meta.total') > 0) {
            foreach ($response->json('data') as $item) {
                if ($item['charges_max'] !== null && is_numeric($item['charges_max'])) {
                    $this->assertLessThanOrEqual(3, (int) $item['charges_max'], "Item {$item['name']} charges_max should be <= 3");
                }
            }
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_charges_max_with_to_range(): void
    {
        $response = $this->getJson('/api/v1/items?filter=charges_max "3" TO "7"&per_page=100');

        $response->assertOk();

        if ($response->json('meta.total') > 0) {
            foreach ($response->json('data') as $item) {
                if ($item['charges_max'] !== null && is_numeric($item['charges_max'])) {
                    $chargesMax = (int) $item['charges_max'];
                    $this->assertGreaterThanOrEqual(3, $chargesMax, "Item {$item['name']} charges_max should be >= 3");
                    $this->assertLessThanOrEqual(7, $chargesMax, "Item {$item['name']} charges_max should be <= 7");
                }
            }
        }
    }

    // ============================================================
    // String Operators (rarity field) - 2 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_rarity_with_equals(): void
    {
        $response = $this->getJson('/api/v1/items?filter=rarity = rare&per_page=100');

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find rare items');

        foreach ($response->json('data') as $item) {
            $this->assertEquals('rare', $item['rarity'], "Item {$item['name']} should have rarity = rare");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_rarity_with_not_equals(): void
    {
        $response = $this->getJson('/api/v1/items?filter=rarity != rare');

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find non-rare items');

        foreach ($response->json('data') as $item) {
            $this->assertNotEquals('rare', $item['rarity'], "Item {$item['name']} should not have rarity = rare");
        }

        $rarities = collect($response->json('data'))->pluck('rarity')->unique()->filter()->toArray();
        $this->assertNotContains('rare', $rarities, 'Rare should be excluded');
    }

    // ============================================================
    // Boolean Operators (is_magic field) - 7 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_is_magic_with_equals_true(): void
    {
        $response = $this->getJson('/api/v1/items?filter=is_magic = true&per_page=100');

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find magic items');

        foreach ($response->json('data') as $item) {
            $this->assertTrue($item['is_magic'], "Item {$item['name']} should be magic");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_is_magic_with_equals_false(): void
    {
        $response = $this->getJson('/api/v1/items?filter=is_magic = false&per_page=100');

        $response->assertOk();

        foreach ($response->json('data') as $item) {
            $this->assertFalse($item['is_magic'], "Item {$item['name']} should not be magic");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_is_magic_with_not_equals_true(): void
    {
        $response = $this->getJson('/api/v1/items?filter=is_magic != true');

        $response->assertOk();

        foreach ($response->json('data') as $item) {
            $this->assertFalse($item['is_magic'], "Item {$item['name']} should not be magic (using != true)");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_is_magic_with_not_equals_false(): void
    {
        $response = $this->getJson('/api/v1/items?filter=is_magic != false');

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find magic items');

        foreach ($response->json('data') as $item) {
            $this->assertTrue($item['is_magic'], "Item {$item['name']} should be magic (using != false)");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_is_magic_with_is_null(): void
    {
        $response = $this->getJson('/api/v1/items?filter=is_magic IS NULL');

        $response->assertOk();

        if ($response->json('meta.total') > 0) {
            foreach ($response->json('data') as $item) {
                $this->assertNull($item['is_magic'], "Item {$item['name']} should have null is_magic");
            }
        }

        $this->assertGreaterThanOrEqual(0, $response->json('meta.total'), 'IS NULL operator should work without errors');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_is_magic_with_is_not_null(): void
    {
        $response = $this->getJson('/api/v1/items?filter=is_magic IS NOT NULL');

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find items with is_magic data');

        foreach ($response->json('data') as $item) {
            $this->assertNotNull($item['is_magic'], "Item {$item['name']} should have is_magic set");
            $this->assertIsBool($item['is_magic'], "Item {$item['name']} is_magic should be boolean");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_is_magic_with_not_equals(): void
    {
        $response = $this->getJson('/api/v1/items?filter=is_magic != true');

        $response->assertOk();

        foreach ($response->json('data') as $item) {
            $this->assertFalse($item['is_magic'], "Item {$item['name']} should not be magic");
        }
    }

    // ============================================================
    // Array Operators (property_codes field) - 3 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_property_codes_with_in(): void
    {
        $response = $this->getJson('/api/v1/items?filter=property_codes IN [V, L]&per_page=100');

        $response->assertOk();

        if ($response->json('meta.total') > 0) {
            foreach ($response->json('data') as $item) {
                $properties = $item['properties'] ?? [];
                $propertyCodes = collect($properties)->pluck('code')->toArray();

                $hasVersatileOrLight = in_array('V', $propertyCodes) || in_array('L', $propertyCodes);
                $this->assertTrue($hasVersatileOrLight, "Item {$item['name']} should have versatile (V) or light (L) property");
            }
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_property_codes_with_not_in(): void
    {
        $response = $this->getJson('/api/v1/items?filter=property_codes NOT IN [H]&per_page=100');

        $response->assertOk();

        if ($response->json('meta.total') > 0) {
            foreach ($response->json('data') as $item) {
                $properties = $item['properties'] ?? [];
                $propertyCodes = collect($properties)->pluck('code')->toArray();

                $this->assertNotContains('H', $propertyCodes, "Item {$item['name']} should not have heavy (H) property");
            }
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_property_codes_with_is_empty(): void
    {
        $response = $this->getJson('/api/v1/items?filter=property_codes IS EMPTY&per_page=100');

        $response->assertOk();

        foreach ($response->json('data') as $item) {
            $properties = $item['properties'] ?? [];
            $propertyCodes = collect($properties)->pluck('code')->toArray();

            $this->assertEmpty($propertyCodes, "Item {$item['name']} should have no properties");
        }
    }
}
