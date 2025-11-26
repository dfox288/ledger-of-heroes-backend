<?php

namespace Tests\Feature\Api;

use App\Models\Item;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for Item filter functionality using Meilisearch.
 *
 * These tests use pre-imported data from SearchTestExtension.
 * No RefreshDatabase needed - all tests are read-only against shared data.
 */
#[\PHPUnit\Framework\Attributes\Group('feature-search')]
#[\PHPUnit\Framework\Attributes\Group('search-isolated')]
class ItemFilterTest extends TestCase
{
    protected $seed = false;

    #[Test]
    public function it_filters_items_by_minimum_strength_requirement()
    {
        // Get count of items with high strength requirement from imported data
        $highStrItems = Item::where('strength_requirement', '>=', 15)->count();

        if ($highStrItems === 0) {
            $this->markTestSkipped('No items with strength_requirement >= 15 in imported data');
        }

        // Test strength_requirement >= 15
        $response = $this->getJson('/api/v1/items?filter=strength_requirement >= 15');
        $response->assertOk();
        $this->assertEquals($highStrItems, $response->json('meta.total'));

        // Verify all returned items have strength_requirement >= 15
        foreach ($response->json('data') as $item) {
            $this->assertGreaterThanOrEqual(15, $item['strength_requirement']);
        }
    }

    #[Test]
    public function it_filters_items_with_prerequisites()
    {
        // Get count of items with any strength requirement from imported data
        $itemsWithStr = Item::whereNotNull('strength_requirement')->count();

        if ($itemsWithStr === 0) {
            $this->markTestSkipped('No items with strength_requirement in imported data');
        }

        // Filter items with strength_requirement >= 0 (any positive value)
        $response = $this->getJson('/api/v1/items?filter=strength_requirement >= 0');
        $response->assertOk();
        $this->assertEquals($itemsWithStr, $response->json('meta.total'));
    }

    #[Test]
    public function it_filters_items_with_new_prerequisite_system()
    {
        // Get items that have prerequisites in the new system
        $itemsWithPrereqs = Item::has('prerequisites')->count();

        if ($itemsWithPrereqs === 0) {
            $this->markTestSkipped('No items with EntityPrerequisite records in imported data');
        }

        // Filter items with has_prerequisites = true
        $response = $this->getJson('/api/v1/items?filter=has_prerequisites = true');
        $response->assertOk();
        $this->assertEquals($itemsWithPrereqs, $response->json('meta.total'));
    }

    #[Test]
    public function it_supports_both_legacy_and_new_prerequisite_systems()
    {
        // Get items with strength requirement from legacy system
        $legacyCount = Item::whereNotNull('strength_requirement')->count();

        // Get items with prerequisites from new system
        $newSystemCount = Item::has('prerequisites')->count();

        // At least one system should have data
        if ($legacyCount === 0 && $newSystemCount === 0) {
            $this->markTestSkipped('No items with prerequisites in either system');
        }

        if ($legacyCount > 0) {
            // Test legacy system filter
            $response = $this->getJson('/api/v1/items?filter=strength_requirement >= 0');
            $response->assertOk();
            $this->assertEquals($legacyCount, $response->json('meta.total'));
        }

        if ($newSystemCount > 0) {
            // Test new system filter
            $response = $this->getJson('/api/v1/items?filter=has_prerequisites = true');
            $response->assertOk();
            $this->assertEquals($newSystemCount, $response->json('meta.total'));
        }
    }

    #[Test]
    public function it_paginates_filtered_items()
    {
        // Get total items with any strength requirement
        $totalItems = Item::whereNotNull('strength_requirement')->count();

        if ($totalItems < 2) {
            $this->markTestSkipped('Not enough items with strength_requirement for pagination test');
        }

        // Request with pagination
        $response = $this->getJson('/api/v1/items?filter=strength_requirement >= 0&per_page=10');
        $response->assertOk();

        // Verify pagination structure
        $this->assertLessThanOrEqual(10, count($response->json('data')));
        $this->assertEquals($totalItems, $response->json('meta.total'));
        $this->assertEquals(10, $response->json('meta.per_page'));
    }

    #[Test]
    public function it_returns_empty_when_no_items_match_strength_requirement()
    {
        // Filter by an unreasonably high strength requirement that no item should have
        $response = $this->getJson('/api/v1/items?filter=strength_requirement >= 99');
        $response->assertOk();

        // Verify no results returned
        $this->assertEquals(0, $response->json('meta.total'));
        $this->assertEmpty($response->json('data'));
    }
}
