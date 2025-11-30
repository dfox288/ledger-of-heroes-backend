<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\ItemResource;
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
}
