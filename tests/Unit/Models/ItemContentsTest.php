<?php

namespace Tests\Unit\Models;

use App\Models\EntityItem;
use App\Models\Item;
use App\Models\ItemType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-db')]
class ItemContentsTest extends TestCase
{
    use RefreshDatabase;

    private ItemType $gearType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gearType = ItemType::firstOrCreate(
            ['code' => 'G'],
            ['name' => 'Adventuring Gear']
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_empty_collection_when_pack_has_no_contents(): void
    {
        $pack = Item::factory()->create([
            'name' => "Explorer's Pack",
            'item_type_id' => $this->gearType->id,
        ]);

        $this->assertCount(0, $pack->contents);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_contents_for_a_pack(): void
    {
        $pack = Item::factory()->create([
            'name' => "Explorer's Pack",
            'item_type_id' => $this->gearType->id,
        ]);

        $backpack = Item::factory()->create([
            'name' => 'Backpack',
            'item_type_id' => $this->gearType->id,
        ]);

        $bedroll = Item::factory()->create([
            'name' => 'Bedroll',
            'item_type_id' => $this->gearType->id,
        ]);

        // Link contents to the pack
        EntityItem::create([
            'reference_type' => Item::class,
            'reference_id' => $pack->id,
            'item_id' => $backpack->id,
            'quantity' => 1,
        ]);

        EntityItem::create([
            'reference_type' => Item::class,
            'reference_id' => $pack->id,
            'item_id' => $bedroll->id,
            'quantity' => 1,
        ]);

        $contents = $pack->contents;

        $this->assertCount(2, $contents);
        $this->assertTrue($contents->contains('item_id', $backpack->id));
        $this->assertTrue($contents->contains('item_id', $bedroll->id));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_contents_with_quantities(): void
    {
        $pack = Item::factory()->create([
            'name' => "Dungeoneer's Pack",
            'item_type_id' => $this->gearType->id,
        ]);

        $torch = Item::factory()->create([
            'name' => 'Torch',
            'item_type_id' => $this->gearType->id,
        ]);

        $rations = Item::factory()->create([
            'name' => 'Rations (1 day)',
            'item_type_id' => $this->gearType->id,
        ]);

        EntityItem::create([
            'reference_type' => Item::class,
            'reference_id' => $pack->id,
            'item_id' => $torch->id,
            'quantity' => 10,
        ]);

        EntityItem::create([
            'reference_type' => Item::class,
            'reference_id' => $pack->id,
            'item_id' => $rations->id,
            'quantity' => 10,
        ]);

        $contents = $pack->contents;

        $torchEntry = $contents->firstWhere('item_id', $torch->id);
        $rationsEntry = $contents->firstWhere('item_id', $rations->id);

        $this->assertEquals(10, $torchEntry->quantity);
        $this->assertEquals(10, $rationsEntry->quantity);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_eager_loads_item_relationship_on_contents(): void
    {
        $pack = Item::factory()->create([
            'name' => "Explorer's Pack",
            'item_type_id' => $this->gearType->id,
        ]);

        $backpack = Item::factory()->create([
            'name' => 'Backpack',
            'item_type_id' => $this->gearType->id,
        ]);

        EntityItem::create([
            'reference_type' => Item::class,
            'reference_id' => $pack->id,
            'item_id' => $backpack->id,
            'quantity' => 1,
        ]);

        $pack->load('contents.item');

        $content = $pack->contents->first();

        $this->assertTrue($content->relationLoaded('item'));
        $this->assertEquals('Backpack', $content->item->name);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_packs_this_item_is_contained_in_via_contained_in_relationship(): void
    {
        $explorersPack = Item::factory()->create([
            'name' => "Explorer's Pack",
            'item_type_id' => $this->gearType->id,
        ]);

        $dungeoneersPack = Item::factory()->create([
            'name' => "Dungeoneer's Pack",
            'item_type_id' => $this->gearType->id,
        ]);

        $backpack = Item::factory()->create([
            'name' => 'Backpack',
            'item_type_id' => $this->gearType->id,
        ]);

        // Backpack is contained in both packs
        EntityItem::create([
            'reference_type' => Item::class,
            'reference_id' => $explorersPack->id,
            'item_id' => $backpack->id,
            'quantity' => 1,
        ]);

        EntityItem::create([
            'reference_type' => Item::class,
            'reference_id' => $dungeoneersPack->id,
            'item_id' => $backpack->id,
            'quantity' => 1,
        ]);

        $containedIn = $backpack->containedIn;

        $this->assertCount(2, $containedIn);

        // Get the pack items from the EntityItem relationships
        $packIds = $containedIn->pluck('reference_id')->toArray();
        $this->assertContains($explorersPack->id, $packIds);
        $this->assertContains($dungeoneersPack->id, $packIds);
    }
}
