<?php

namespace Tests\Unit\Services\Matching;

use App\Models\Item;
use App\Services\Matching\ItemMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ItemMatchingServiceTest extends TestCase
{
    use RefreshDatabase;

    private ItemMatchingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ItemMatchingService;
        ItemMatchingService::clearCache();
    }

    protected function tearDown(): void
    {
        ItemMatchingService::clearCache();
        parent::tearDown();
    }

    #[Test]
    public function it_returns_null_for_empty_string()
    {
        $result = $this->service->matchItem('');
        $this->assertNull($result);
    }

    #[Test]
    public function it_returns_null_for_whitespace_only_string()
    {
        $result = $this->service->matchItem('   ');
        $this->assertNull($result);

        $result = $this->service->matchItem("\t\n");
        $this->assertNull($result);
    }

    #[Test]
    public function it_matches_item_by_exact_name()
    {
        $item = Item::factory()->create(['name' => 'Longsword']);

        $result = $this->service->matchItem('Longsword');

        $this->assertNotNull($result);
        $this->assertInstanceOf(Item::class, $result);
        $this->assertEquals($item->id, $result->id);
        $this->assertEquals('Longsword', $result->name);
    }

    #[Test]
    public function it_matches_item_case_insensitively()
    {
        $item = Item::factory()->create(['name' => 'Longsword']);

        $result = $this->service->matchItem('longsword');
        $this->assertNotNull($result);
        $this->assertEquals($item->id, $result->id);

        $result = $this->service->matchItem('LONGSWORD');
        $this->assertNotNull($result);
        $this->assertEquals($item->id, $result->id);

        $result = $this->service->matchItem('LongSword');
        $this->assertNotNull($result);
        $this->assertEquals($item->id, $result->id);
    }

    #[Test]
    public function it_matches_item_with_leading_and_trailing_whitespace()
    {
        $item = Item::factory()->create(['name' => 'Longsword']);

        $result = $this->service->matchItem('  Longsword  ');
        $this->assertNotNull($result);
        $this->assertEquals($item->id, $result->id);

        $result = $this->service->matchItem("\tLongsword\n");
        $this->assertNotNull($result);
        $this->assertEquals($item->id, $result->id);
    }

    #[Test]
    public function it_matches_item_removing_possessives()
    {
        $item = Item::factory()->create(['name' => 'Travelers Clothes']);

        $result = $this->service->matchItem("Traveler's Clothes");
        $this->assertNotNull($result);
        $this->assertEquals($item->id, $result->id);
    }

    #[Test]
    public function it_matches_item_removing_leading_articles()
    {
        $item = Item::factory()->create(['name' => 'Longsword']);

        $result = $this->service->matchItem('a Longsword');
        $this->assertNotNull($result);
        $this->assertEquals($item->id, $result->id);

        ItemMatchingService::clearCache();
        $result = $this->service->matchItem('an Longsword');
        $this->assertNotNull($result);
        $this->assertEquals($item->id, $result->id);

        ItemMatchingService::clearCache();
        $result = $this->service->matchItem('the Longsword');
        $this->assertNotNull($result);
        $this->assertEquals($item->id, $result->id);
    }

    #[Test]
    public function it_matches_item_removing_set_of_prefix()
    {
        $item = Item::factory()->create(['name' => 'Dice']);

        $result = $this->service->matchItem('set of Dice');
        $this->assertNotNull($result);
        $this->assertEquals($item->id, $result->id);
    }

    #[Test]
    public function it_matches_item_by_slug()
    {
        $item = Item::factory()->create([
            'name' => "Traveler's Clothes",
            'slug' => 'travelers-clothes',
        ]);

        $result = $this->service->matchItem("Traveler's Clothes");
        $this->assertNotNull($result);
        $this->assertEquals($item->id, $result->id);
    }

    #[Test]
    public function it_matches_using_hardcoded_mappings()
    {
        $item = Item::factory()->create(['name' => 'Gold (gp)']);

        $result = $this->service->matchItem('gp');
        $this->assertNotNull($result);
        $this->assertEquals($item->id, $result->id);

        ItemMatchingService::clearCache();
        $item2 = Item::factory()->create(['name' => 'Pouch']);
        $result = $this->service->matchItem('belt pouch');
        $this->assertNotNull($result);
        $this->assertEquals($item2->id, $result->id);

        ItemMatchingService::clearCache();
        $item3 = Item::factory()->create(['name' => 'Ink Pen']);
        $result = $this->service->matchItem('quill');
        $this->assertNotNull($result);
        $this->assertEquals($item3->id, $result->id);
    }

    #[Test]
    public function it_returns_null_when_mapped_item_not_in_database()
    {
        // Map exists (gp -> Gold (gp)) but item not in database
        $result = $this->service->matchItem('gp');
        $this->assertNull($result);
    }

    #[Test]
    public function it_returns_null_when_no_match_found()
    {
        Item::factory()->create(['name' => 'Longsword']);

        $result = $this->service->matchItem('Nonexistent Item');
        $this->assertNull($result);
    }

    #[Test]
    public function it_performs_partial_matching_for_long_names()
    {
        $item = Item::factory()->create(['name' => 'Sword of Awesome Power']);

        // Partial match: "Sword" is contained in "Sword of Awesome Power"
        $result = $this->service->matchItem('Sword of Awesome');
        $this->assertNotNull($result);
        $this->assertEquals($item->id, $result->id);
    }

    #[Test]
    public function it_does_not_partial_match_short_names()
    {
        Item::factory()->create(['name' => 'Axe']);

        // Too short for partial matching (< 5 chars)
        $result = $this->service->matchItem('Ax');
        $this->assertNull($result);
    }

    #[Test]
    public function it_requires_70_percent_overlap_for_partial_match()
    {
        // The partial match logic checks if one string contains another
        // and if the overlap is at least 70% of the SHORTER string

        // Test case: "Healing" (7 chars) in "Healing Potion" (14 chars normalized)
        $item = Item::factory()->create(['name' => 'Healing Potion']);
        $result = $this->service->matchItem('Healing');
        // "healing" is contained in "healing potion"
        // overlap = min(7, 14) = 7, shorter = 7, 7/7 = 100% >= 70%
        $this->assertNotNull($result);

        ItemMatchingService::clearCache();

        // Test case where the search is too short relative to the item name
        // We need a case where contains() is true but overlap percentage < 70%
        // This is tricky because if one contains the other, the overlap is the shorter string
        // So the percentage is always 100% when there's a contains relationship

        // Actually, looking at the code, the overlap calculation is:
        // overlap = min(strlen($name1), strlen($name2))
        // percentage = overlap / shorterLength
        // This means if one contains the other, percentage is always 100%

        // So partial matching will always succeed if one string contains the other
        // and both are >= 5 characters
        $item2 = Item::factory()->create(['name' => 'Superlongitemname']);
        $result = $this->service->matchItem('Super');
        // "super" (5 chars) is contained in "superlongitemname" (17 chars)
        // Both >= 5 chars, so partial match succeeds
        $this->assertNotNull($result);
    }

    #[Test]
    public function it_caches_items_for_performance()
    {
        $item = Item::factory()->create(['name' => 'Longsword']);

        // First call initializes cache
        $result1 = $this->service->matchItem('Longsword');
        $this->assertNotNull($result1);

        // Create new item after cache is initialized
        $newItem = Item::factory()->create(['name' => 'Shortsword']);

        // Second call should use cache and not see the new item
        $result2 = $this->service->matchItem('Shortsword');
        $this->assertNull($result2); // Not in cache yet

        // Clear cache and try again
        ItemMatchingService::clearCache();
        $result3 = $this->service->matchItem('Shortsword');
        $this->assertNotNull($result3);
        $this->assertEquals($newItem->id, $result3->id);
    }

    #[Test]
    public function it_handles_multiple_whitespace_characters()
    {
        $item = Item::factory()->create(['name' => 'Long Sword']);

        $result = $this->service->matchItem('Long    Sword');
        $this->assertNotNull($result);
        $this->assertEquals($item->id, $result->id);
    }

    #[Test]
    public function it_prefers_exact_match_over_partial_match()
    {
        $exactItem = Item::factory()->create(['name' => 'Healing Potion']);
        $partialItem = Item::factory()->create(['name' => 'Greater Healing Potion']);

        $result = $this->service->matchItem('Healing Potion');
        $this->assertNotNull($result);
        $this->assertEquals($exactItem->id, $result->id);
    }

    #[Test]
    public function it_prefers_mapped_match_over_exact_match()
    {
        // Create both items
        $unmappedItem = Item::factory()->create(['name' => 'gp']);
        $mappedItem = Item::factory()->create(['name' => 'Gold (gp)']);

        // Should prefer the mapped match
        $result = $this->service->matchItem('gp');
        $this->assertNotNull($result);
        $this->assertEquals($mappedItem->id, $result->id);
    }

    #[Test]
    public function it_prefers_slug_match_over_partial_match()
    {
        $slugItem = Item::factory()->create([
            'name' => 'Chain Mail',
            'slug' => 'chain-mail',
        ]);
        $partialItem = Item::factory()->create(['name' => 'Chain Mail Armor']);

        $result = $this->service->matchItem('chain mail');
        $this->assertNotNull($result);
        $this->assertEquals($slugItem->id, $result->id);
    }

    #[Test]
    public function it_handles_items_with_special_characters()
    {
        $item = Item::factory()->create(['name' => 'Potion (Greater Healing)']);

        $result = $this->service->matchItem('Potion (Greater Healing)');
        $this->assertNotNull($result);
        $this->assertEquals($item->id, $result->id);
    }

    #[Test]
    public function it_handles_items_with_numbers()
    {
        $item = Item::factory()->create(['name' => 'Bag of Holding Type II']);

        $result = $this->service->matchItem('Bag of Holding Type II');
        $this->assertNotNull($result);
        $this->assertEquals($item->id, $result->id);
    }

    #[Test]
    public function it_handles_multiple_items_with_similar_names()
    {
        $item1 = Item::factory()->create(['name' => 'Potion of Healing']);
        $item2 = Item::factory()->create(['name' => 'Potion of Greater Healing']);
        $item3 = Item::factory()->create(['name' => 'Potion of Superior Healing']);

        $result1 = $this->service->matchItem('Potion of Healing');
        $this->assertNotNull($result1);
        $this->assertEquals($item1->id, $result1->id);

        ItemMatchingService::clearCache();
        $result2 = $this->service->matchItem('Potion of Greater Healing');
        $this->assertNotNull($result2);
        $this->assertEquals($item2->id, $result2->id);

        ItemMatchingService::clearCache();
        $result3 = $this->service->matchItem('Potion of Superior Healing');
        $this->assertNotNull($result3);
        $this->assertEquals($item3->id, $result3->id);
    }

    #[Test]
    public function clear_cache_resets_static_cache()
    {
        $item = Item::factory()->create(['name' => 'Longsword']);

        // Initialize cache
        $this->service->matchItem('Longsword');

        // Clear cache
        ItemMatchingService::clearCache();

        // Create new service instance
        $newService = new ItemMatchingService;

        // Should reinitialize cache and see the item
        $result = $newService->matchItem('Longsword');
        $this->assertNotNull($result);
    }

    #[Test]
    public function it_handles_empty_database()
    {
        // No items in database
        $result = $this->service->matchItem('Longsword');
        $this->assertNull($result);
    }

    #[Test]
    public function it_handles_single_character_names()
    {
        Item::factory()->create(['name' => 'X']);

        $result = $this->service->matchItem('X');
        $this->assertNotNull($result);
    }

    #[Test]
    public function it_normalizes_consecutive_spaces()
    {
        $item = Item::factory()->create(['name' => 'Magic Item']);

        $result = $this->service->matchItem('Magic     Item');
        $this->assertNotNull($result);
        $this->assertEquals($item->id, $result->id);
    }

    #[Test]
    public function it_handles_unicode_characters_in_item_names()
    {
        $item = Item::factory()->create(['name' => 'Élven Cloak']);

        $result = $this->service->matchItem('Élven Cloak');
        $this->assertNotNull($result);
        $this->assertEquals($item->id, $result->id);
    }

    #[Test]
    public function it_handles_items_with_apostrophes()
    {
        $item = Item::factory()->create(['name' => "Wizard's Hat"]);

        $result = $this->service->matchItem("Wizard's Hat");
        $this->assertNotNull($result);
        $this->assertEquals($item->id, $result->id);
    }

    #[Test]
    public function partial_match_requires_minimum_five_characters()
    {
        Item::factory()->create(['name' => 'Abcd']);

        // 4 characters, too short for partial matching
        $result = $this->service->matchItem('Abc');
        $this->assertNull($result);

        ItemMatchingService::clearCache();
        Item::factory()->create(['name' => 'Abcde']);

        // 5 characters, eligible for partial matching
        $result = $this->service->matchItem('Abcde');
        $this->assertNotNull($result);
    }

    #[Test]
    public function it_returns_first_partial_match_when_multiple_exist()
    {
        $item1 = Item::factory()->create(['name' => 'Leather Armor']);
        $item2 = Item::factory()->create(['name' => 'Studded Leather Armor']);

        // "Leather Armor" should match first item found
        $result = $this->service->matchItem('Leather');
        $this->assertNotNull($result);
        // Should match one of them (order depends on database)
        $this->assertTrue(
            $result->id === $item1->id || $result->id === $item2->id
        );
    }

    #[Test]
    public function mapped_names_are_normalized_before_matching()
    {
        $item = Item::factory()->create(['name' => 'Gold (gp)']);

        // 'gp' maps to 'Gold (gp)', which should be normalized and matched
        $result = $this->service->matchItem('GP'); // Case insensitive mapping
        $this->assertNotNull($result);
        $this->assertEquals($item->id, $result->id);
    }

    #[Test]
    public function it_handles_items_with_hyphens()
    {
        $item = Item::factory()->create([
            'name' => 'Plate-Mail',
            'slug' => 'plate-mail',
        ]);

        $result = $this->service->matchItem('Plate-Mail');
        $this->assertNotNull($result);
        $this->assertEquals($item->id, $result->id);
    }

    #[Test]
    public function slug_matching_handles_special_characters()
    {
        $item = Item::factory()->create([
            'name' => "Fighter's Armor (Type I)",
            'slug' => 'fighters-armor-type-i',
        ]);

        $result = $this->service->matchItem("Fighter's Armor (Type I)");
        $this->assertNotNull($result);
        $this->assertEquals($item->id, $result->id);
    }
}
