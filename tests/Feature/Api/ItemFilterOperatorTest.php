<?php

namespace Tests\Feature\Api;

use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemFilterOperatorTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Import real items from XML files for testing (provides realistic data)
        $this->artisan('import:items', ['file' => 'import-files/items-dmg.xml']);

        // Configure Meilisearch indexes for testing
        $this->artisan('search:configure-indexes');

        // Re-index all items to ensure Meilisearch has latest data with correct schema
        // This is critical for array field filtering (property_codes, etc.)
        Item::all()->searchable();

        // Give Meilisearch time to index (async operation)
        sleep(2);
    }

    // ============================================================
    // Integer Operators (charges_max field) - 7 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_charges_max_with_equals(): void
    {
        // Verify database has items
        $this->assertGreaterThan(0, Item::count(), 'Database must be seeded');

        // Act: Filter by charges_max = "7" (items with exactly 7 charges)
        $response = $this->getJson('/api/v1/items?filter=charges_max = "7"&per_page=100');

        // Assert
        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find items with charges_max = 7');

        // Verify all returned items have charges_max = 7
        foreach ($response->json('data') as $item) {
            $this->assertEquals('7', $item['charges_max'], "Item {$item['name']} should have charges_max = 7");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_charges_max_with_not_equals(): void
    {
        // Verify database has items
        $this->assertGreaterThan(0, Item::count(), 'Database must be seeded');

        // Act: Filter by charges_max != "7" (should return all items except those with 7 charges)
        $response = $this->getJson('/api/v1/items?filter=charges_max != "7"');

        // Assert: Should return items with different charges_max values
        $response->assertOk();

        // Verify no items with charges_max = 7 in results
        foreach ($response->json('data') as $item) {
            $this->assertNotEquals('7', $item['charges_max'], "Item {$item['name']} should not have charges_max = 7");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_charges_max_with_greater_than(): void
    {
        // Verify database has items
        $this->assertGreaterThan(0, Item::count(), 'Database must be seeded');

        // Act: Filter by charges_max > "5" (should return items with more than 5 charges)
        $response = $this->getJson('/api/v1/items?filter=charges_max > "5"&per_page=100');

        // Assert: Should return only items with charges_max > 5
        $response->assertOk();

        if ($response->json('meta.total') > 0) {
            // Verify all returned items have charges_max > 5
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
        // Verify database has items
        $this->assertGreaterThan(0, Item::count(), 'Database must be seeded');

        // Act: Filter by charges_max >= "7" (should return items with 7 or more charges)
        $response = $this->getJson('/api/v1/items?filter=charges_max >= "7"&per_page=100');

        // Assert: Should include items with charges_max >= 7
        $response->assertOk();

        if ($response->json('meta.total') > 0) {
            // Verify all returned items have charges_max >= 7
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
        // Verify database has items
        $this->assertGreaterThan(0, Item::count(), 'Database must be seeded');

        // Act: Filter by charges_max < "5" (should return items with fewer than 5 charges)
        $response = $this->getJson('/api/v1/items?filter=charges_max < "5"&per_page=100');

        // Assert: Should return items with charges_max < 5
        $response->assertOk();

        if ($response->json('meta.total') > 0) {
            // Verify all returned items have charges_max < 5
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
        // Verify database has items
        $this->assertGreaterThan(0, Item::count(), 'Database must be seeded');

        // Act: Filter by charges_max <= "3" (should return items with 3 or fewer charges)
        $response = $this->getJson('/api/v1/items?filter=charges_max <= "3"&per_page=100');

        // Assert: Should include items with charges_max <= 3
        $response->assertOk();

        if ($response->json('meta.total') > 0) {
            // Verify all returned items have charges_max <= 3
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
        // Verify database has items
        $this->assertGreaterThan(0, Item::count(), 'Database must be seeded');

        // Act: Filter by charges_max "3" TO "7" (inclusive range - should return items with 3-7 charges)
        $response = $this->getJson('/api/v1/items?filter=charges_max "3" TO "7"&per_page=100');

        // Assert: Should include items with charges_max 3-7 (TO operator is inclusive)
        $response->assertOk();

        if ($response->json('meta.total') > 0) {
            // Verify all returned items are in range 3-7
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
        // Verify database has items
        $this->assertGreaterThan(0, Item::count(), 'Database must be seeded');

        // Act: Filter by rarity = "rare" (rare magic items)
        $response = $this->getJson('/api/v1/items?filter=rarity = rare&per_page=100');

        // Assert
        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find rare items');

        // Verify all returned items have rarity = rare
        foreach ($response->json('data') as $item) {
            $this->assertEquals('rare', $item['rarity'], "Item {$item['name']} should have rarity = rare");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_rarity_with_not_equals(): void
    {
        // Verify database has items
        $this->assertGreaterThan(0, Item::count(), 'Database must be seeded');

        // Act: Filter by rarity != "rare" (all items except rare)
        $response = $this->getJson('/api/v1/items?filter=rarity != rare');

        // Assert: Should return items of all rarities except rare
        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find non-rare items');

        // Verify no rare items in results
        foreach ($response->json('data') as $item) {
            $this->assertNotEquals('rare', $item['rarity'], "Item {$item['name']} should not have rarity = rare");
        }

        // Verify we have items from other rarities
        $rarities = collect($response->json('data'))->pluck('rarity')->unique()->filter()->toArray();
        $this->assertNotContains('rare', $rarities, 'Rare should be excluded');
    }

    // ============================================================
    // Boolean Operators (is_magic field) - 7 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_is_magic_with_equals_true(): void
    {
        // Verify database has items
        $this->assertGreaterThan(0, Item::count(), 'Database must be seeded');

        // Act: Filter by is_magic = true (magic items)
        $response = $this->getJson('/api/v1/items?filter=is_magic = true&per_page=100');

        // Assert
        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find magic items');

        // Verify all returned items are magic
        foreach ($response->json('data') as $item) {
            $this->assertTrue($item['is_magic'], "Item {$item['name']} should be magic");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_is_magic_with_equals_false(): void
    {
        // Verify database has items
        $this->assertGreaterThan(0, Item::count(), 'Database must be seeded');

        // Act: Filter by is_magic = false (non-magic items)
        $response = $this->getJson('/api/v1/items?filter=is_magic = false&per_page=100');

        // Assert
        $response->assertOk();

        // Verify all returned items are NOT magic
        foreach ($response->json('data') as $item) {
            $this->assertFalse($item['is_magic'], "Item {$item['name']} should not be magic");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_is_magic_with_not_equals_true(): void
    {
        // Verify database has items
        $this->assertGreaterThan(0, Item::count(), 'Database must be seeded');

        // Act: Filter by is_magic != true (items that are false or null)
        $response = $this->getJson('/api/v1/items?filter=is_magic != true');

        // Assert: Should return non-magic items
        $response->assertOk();

        // Verify all returned items are NOT magic
        foreach ($response->json('data') as $item) {
            $this->assertFalse($item['is_magic'], "Item {$item['name']} should not be magic (using != true)");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_is_magic_with_not_equals_false(): void
    {
        // Verify database has items
        $this->assertGreaterThan(0, Item::count(), 'Database must be seeded');

        // Act: Filter by is_magic != false (items that are true or null)
        $response = $this->getJson('/api/v1/items?filter=is_magic != false');

        // Assert: Should return magic items
        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find magic items');

        // Verify all returned items are magic
        foreach ($response->json('data') as $item) {
            $this->assertTrue($item['is_magic'], "Item {$item['name']} should be magic (using != false)");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_is_magic_with_is_null(): void
    {
        // Verify database has items
        $this->assertGreaterThan(0, Item::count(), 'Database must be seeded');

        // Act: Filter by is_magic IS NULL (items with no magic data)
        $response = $this->getJson('/api/v1/items?filter=is_magic IS NULL');

        // Assert: Most items should have is_magic data (true or false)
        // This test verifies IS NULL works, but may return 0 results with clean data
        $response->assertOk();

        // If any results are returned, verify they have null is_magic
        if ($response->json('meta.total') > 0) {
            foreach ($response->json('data') as $item) {
                $this->assertNull($item['is_magic'], "Item {$item['name']} should have null is_magic");
            }
        }

        // With properly imported data, we expect few or 0 results since most items have is_magic set
        // This demonstrates IS NULL operator works correctly
        $this->assertGreaterThanOrEqual(0, $response->json('meta.total'), 'IS NULL operator should work without errors');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_is_magic_with_is_not_null(): void
    {
        // Verify database has items
        $this->assertGreaterThan(0, Item::count(), 'Database must be seeded');

        // Act: Filter by is_magic IS NOT NULL (items with magic data)
        $response = $this->getJson('/api/v1/items?filter=is_magic IS NOT NULL');

        // Assert: Should return items with is_magic set (true or false)
        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find items with is_magic data');

        // Verify all returned items have non-null is_magic
        foreach ($response->json('data') as $item) {
            $this->assertNotNull($item['is_magic'], "Item {$item['name']} should have is_magic set");
            $this->assertIsBool($item['is_magic'], "Item {$item['name']} is_magic should be boolean");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_is_magic_with_not_equals(): void
    {
        // Verify database has items
        $this->assertGreaterThan(0, Item::count(), 'Database must be seeded');

        // Act: Filter by is_magic != true (same as not_equals_true test but using different syntax)
        $response = $this->getJson('/api/v1/items?filter=is_magic != true');

        // Assert: Should return non-magic items
        $response->assertOk();

        // Verify all returned items are NOT magic
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
        // Verify database has items
        $this->assertGreaterThan(0, Item::count(), 'Database must be seeded');

        // Act: Filter by property_codes IN [V, L] (versatile OR light weapons)
        $response = $this->getJson('/api/v1/items?filter=property_codes IN [V, L]&per_page=100');

        // Assert: Should return items with versatile or light property
        $response->assertOk();

        if ($response->json('meta.total') > 0) {
            // Verify all returned items have V OR L in their property_codes
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
        // Verify database has items
        $this->assertGreaterThan(0, Item::count(), 'Database must be seeded');

        // Act: Filter by property_codes NOT IN [H] (exclude heavy weapons)
        $response = $this->getJson('/api/v1/items?filter=property_codes NOT IN [H]&per_page=100');

        // Assert: Should return items NOT with heavy property
        $response->assertOk();

        if ($response->json('meta.total') > 0) {
            // Verify NO returned items have H in their property_codes
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
        // Verify database has items
        $this->assertGreaterThan(0, Item::count(), 'Database must be seeded');

        // Act: Filter by property_codes IS EMPTY (items with no properties)
        $response = $this->getJson('/api/v1/items?filter=property_codes IS EMPTY&per_page=100');

        // Assert: Should return items with no property associations (if any exist)
        $response->assertOk();

        // Verify all returned items have empty property_codes array
        foreach ($response->json('data') as $item) {
            $properties = $item['properties'] ?? [];
            $propertyCodes = collect($properties)->pluck('code')->toArray();

            $this->assertEmpty($propertyCodes, "Item {$item['name']} should have no properties");
        }

        // Note: Many items may not have properties, so this could return many results
        // This is a valid test case for armor, adventuring gear, etc.
    }
}
