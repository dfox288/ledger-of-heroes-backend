<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\ItemResource;
use App\Models\EntityItem;
use App\Models\Item;
use App\Models\ItemProperty;
use App\Models\ItemType;
use App\Models\Modifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-db')]
class ItemResourceTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_proficiency_category_in_response(): void
    {
        $meleeType = ItemType::firstOrCreate(
            ['code' => 'M'],
            ['name' => 'Melee Weapon']
        );

        $martialProperty = ItemProperty::firstOrCreate(
            ['code' => 'M'],
            ['name' => 'Martial']
        );

        $item = Item::factory()->create([
            'name' => 'Longsword',
            'item_type_id' => $meleeType->id,
        ]);

        $item->properties()->attach($martialProperty->id);
        $item->load('properties', 'itemType');

        $resource = new ItemResource($item);
        $response = $resource->toArray(request());

        $this->assertArrayHasKey('proficiency_category', $response);
        $this->assertEquals('martial_melee', $response['proficiency_category']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_magic_bonus_in_response(): void
    {
        $meleeType = ItemType::firstOrCreate(
            ['code' => 'M'],
            ['name' => 'Melee Weapon']
        );

        $item = Item::factory()->create([
            'name' => 'Longsword +2',
            'item_type_id' => $meleeType->id,
            'is_magic' => true,
        ]);

        Modifier::create([
            'reference_type' => Item::class,
            'reference_id' => $item->id,
            'modifier_category' => 'weapon_attack',
            'value' => '2',
        ]);

        $item->load('modifiers');

        $resource = new ItemResource($item);
        $response = $resource->toArray(request());

        $this->assertArrayHasKey('magic_bonus', $response);
        $this->assertEquals(2, $response['magic_bonus']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_null_proficiency_category_for_non_weapons(): void
    {
        $gearType = ItemType::firstOrCreate(
            ['code' => 'G'],
            ['name' => 'Adventuring Gear']
        );

        $item = Item::factory()->create([
            'name' => 'Rope',
            'item_type_id' => $gearType->id,
        ]);

        $item->load('properties', 'itemType');

        $resource = new ItemResource($item);
        $response = $resource->toArray(request());

        $this->assertArrayHasKey('proficiency_category', $response);
        $this->assertNull($response['proficiency_category']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_null_magic_bonus_for_non_magic_items(): void
    {
        $meleeType = ItemType::firstOrCreate(
            ['code' => 'M'],
            ['name' => 'Melee Weapon']
        );

        $item = Item::factory()->create([
            'name' => 'Longsword',
            'item_type_id' => $meleeType->id,
            'is_magic' => false,
        ]);

        $item->load('modifiers');

        $resource = new ItemResource($item);
        $response = $resource->toArray(request());

        $this->assertArrayHasKey('magic_bonus', $response);
        $this->assertNull($response['magic_bonus']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_contents_for_pack_items(): void
    {
        $gearType = ItemType::firstOrCreate(
            ['code' => 'G'],
            ['name' => 'Adventuring Gear']
        );

        $pack = Item::factory()->create([
            'name' => "Explorer's Pack",
            'item_type_id' => $gearType->id,
        ]);

        $backpack = Item::factory()->create([
            'name' => 'Backpack',
            'item_type_id' => $gearType->id,
        ]);

        $torch = Item::factory()->create([
            'name' => 'Torch',
            'item_type_id' => $gearType->id,
        ]);

        EntityItem::create([
            'reference_type' => Item::class,
            'reference_id' => $pack->id,
            'item_id' => $backpack->id,
            'quantity' => 1,
        ]);

        EntityItem::create([
            'reference_type' => Item::class,
            'reference_id' => $pack->id,
            'item_id' => $torch->id,
            'quantity' => 10,
        ]);

        $pack->load('contents.item');

        $resource = new ItemResource($pack);
        $response = $resource->toArray(request());

        $this->assertArrayHasKey('contents', $response);
        $this->assertCount(2, $response['contents']);

        // Check the structure of contents
        $backpackContent = collect($response['contents'])->firstWhere('item.name', 'Backpack');
        $torchContent = collect($response['contents'])->firstWhere('item.name', 'Torch');

        $this->assertEquals(1, $backpackContent['quantity']);
        $this->assertEquals(10, $torchContent['quantity']);
        $this->assertArrayHasKey('id', $backpackContent['item']);
        $this->assertArrayHasKey('slug', $backpackContent['item']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_empty_array_for_items_without_contents(): void
    {
        $gearType = ItemType::firstOrCreate(
            ['code' => 'G'],
            ['name' => 'Adventuring Gear']
        );

        $item = Item::factory()->create([
            'name' => 'Backpack',
            'item_type_id' => $gearType->id,
        ]);

        $item->load('contents');

        $resource = new ItemResource($item);
        $response = $resource->toArray(request());

        $this->assertArrayHasKey('contents', $response);
        $this->assertCount(0, $response['contents']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_equipment_slot_in_response(): void
    {
        $wondrousType = ItemType::firstOrCreate(
            ['code' => 'W'],
            ['name' => 'Wondrous Item']
        );

        $item = Item::factory()->create([
            'name' => 'Cloak of Protection',
            'item_type_id' => $wondrousType->id,
            'equipment_slot' => 'cloak',
        ]);

        $resource = new ItemResource($item);
        $response = $resource->toArray(request());

        $this->assertArrayHasKey('equipment_slot', $response);
        $this->assertEquals('cloak', $response['equipment_slot']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_null_equipment_slot_for_items_without_body_slot(): void
    {
        $gearType = ItemType::firstOrCreate(
            ['code' => 'G'],
            ['name' => 'Adventuring Gear']
        );

        $item = Item::factory()->create([
            'name' => 'Torch',
            'item_type_id' => $gearType->id,
            'equipment_slot' => null,
        ]);

        $resource = new ItemResource($item);
        $response = $resource->toArray(request());

        $this->assertArrayHasKey('equipment_slot', $response);
        $this->assertNull($response['equipment_slot']);
    }
}
