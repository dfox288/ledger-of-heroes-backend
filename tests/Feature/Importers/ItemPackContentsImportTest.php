<?php

namespace Tests\Feature\Importers;

use App\Models\EntityItem;
use App\Models\Item;
use App\Models\ItemType;
use App\Services\Importers\Concerns\ImportsPackContents;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for the ImportsPackContents trait that links equipment packs
 * to their contained items.
 */
#[\PHPUnit\Framework\Attributes\Group('importers')]
class ItemPackContentsImportTest extends TestCase
{
    use RefreshDatabase;

    private ItemType $gearType;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed item types
        if (ItemType::count() === 0) {
            $this->seed(\Database\Seeders\ItemTypeSeeder::class);
        }

        $this->gearType = ItemType::where('code', 'G')->first();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_links_pack_contents_to_existing_items(): void
    {
        // Create the pack with description
        $pack = Item::factory()->create([
            'name' => "Explorer's Pack",
            'slug' => 'explorers-pack',
            'item_type_id' => $this->gearType->id,
            'description' => "Includes:\n\n\t• a backpack\n\t• a bedroll\n\t• 10 torches\n\nSource:\tPlayer's Handbook (2014) p. 151",
        ]);

        // Create the content items
        $backpack = Item::factory()->create([
            'name' => 'Backpack',
            'slug' => 'backpack',
            'item_type_id' => $this->gearType->id,
        ]);

        $bedroll = Item::factory()->create([
            'name' => 'Bedroll',
            'slug' => 'bedroll',
            'item_type_id' => $this->gearType->id,
        ]);

        $torch = Item::factory()->create([
            'name' => 'Torch',
            'slug' => 'torch',
            'item_type_id' => $this->gearType->id,
        ]);

        // Use the trait directly
        $importer = new class
        {
            use ImportsPackContents;
        };

        $importer->importPackContents($pack);

        // Assert contents were linked
        $contents = $pack->fresh()->contents()->with('item')->get();

        $this->assertCount(3, $contents);

        $backpackContent = $contents->firstWhere('item_id', $backpack->id);
        $this->assertNotNull($backpackContent);
        $this->assertEquals(1, $backpackContent->quantity);

        $bedrollContent = $contents->firstWhere('item_id', $bedroll->id);
        $this->assertNotNull($bedrollContent);
        $this->assertEquals(1, $bedrollContent->quantity);

        $torchContent = $contents->firstWhere('item_id', $torch->id);
        $this->assertNotNull($torchContent);
        $this->assertEquals(10, $torchContent->quantity);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_skips_items_that_do_not_exist_in_database(): void
    {
        // Create the pack with description including non-existent item
        $pack = Item::factory()->create([
            'name' => "Priest's Pack",
            'slug' => 'priests-pack',
            'item_type_id' => $this->gearType->id,
            'description' => "Includes:\n\n\t• a backpack\n\t• 2 blocks of incense\n\nSource:\tPlayer's Handbook (2014) p. 151",
        ]);

        // Only create backpack, not incense
        $backpack = Item::factory()->create([
            'name' => 'Backpack',
            'slug' => 'backpack',
            'item_type_id' => $this->gearType->id,
        ]);

        $importer = new class
        {
            use ImportsPackContents;
        };

        $importer->importPackContents($pack);

        // Only backpack should be linked
        $contents = $pack->fresh()->contents;

        $this->assertCount(1, $contents);
        $this->assertEquals($backpack->id, $contents->first()->item_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_clears_existing_contents_before_importing(): void
    {
        // Create pack
        $pack = Item::factory()->create([
            'name' => "Explorer's Pack",
            'slug' => 'explorers-pack',
            'item_type_id' => $this->gearType->id,
            'description' => "Includes:\n\n\t• a backpack\n\nSource:\tPlayer's Handbook (2014) p. 151",
        ]);

        // Create items
        $backpack = Item::factory()->create([
            'name' => 'Backpack',
            'slug' => 'backpack',
            'item_type_id' => $this->gearType->id,
        ]);

        $oldItem = Item::factory()->create([
            'name' => 'Old Item',
            'slug' => 'old-item',
            'item_type_id' => $this->gearType->id,
        ]);

        // Add old content manually
        EntityItem::create([
            'reference_type' => Item::class,
            'reference_id' => $pack->id,
            'item_id' => $oldItem->id,
            'quantity' => 5,
        ]);

        $this->assertCount(1, $pack->fresh()->contents);

        // Run importer
        $importer = new class
        {
            use ImportsPackContents;
        };

        $importer->importPackContents($pack);

        // Old content should be replaced with new
        $contents = $pack->fresh()->contents;

        $this->assertCount(1, $contents);
        $this->assertEquals($backpack->id, $contents->first()->item_id);
        $this->assertEquals(1, $contents->first()->quantity);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_nothing_for_items_without_includes_section(): void
    {
        // Create a regular item (not a pack)
        $item = Item::factory()->create([
            'name' => 'Backpack',
            'slug' => 'backpack',
            'item_type_id' => $this->gearType->id,
            'description' => 'A backpack can hold one cubic foot or 30 pounds of gear.',
        ]);

        $importer = new class
        {
            use ImportsPackContents;
        };

        $importer->importPackContents($item);

        // No contents should be created
        $this->assertCount(0, $item->fresh()->contents);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_matches_items_case_insensitively(): void
    {
        $pack = Item::factory()->create([
            'name' => "Burglar's Pack",
            'slug' => 'burglars-pack',
            'item_type_id' => $this->gearType->id,
            'description' => "Includes:\n\n\t• 5 days Rations\n\nSource:\tPlayer's Handbook (2014) p. 151",
        ]);

        // Create with different case
        $rations = Item::factory()->create([
            'name' => 'Rations (1 day)',
            'slug' => 'rations-1-day',
            'item_type_id' => $this->gearType->id,
        ]);

        $importer = new class
        {
            use ImportsPackContents;
        };

        $importer->importPackContents($pack);

        $contents = $pack->fresh()->contents;

        $this->assertCount(1, $contents);
        $this->assertEquals($rations->id, $contents->first()->item_id);
        $this->assertEquals(5, $contents->first()->quantity);
    }
}
