<?php

namespace Tests\Unit\Models;

use App\Models\Item;
use App\Models\ItemProperty;
use App\Models\ItemType;
use App\Models\Modifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-db')]
class ItemAccessorsTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // proficiency_category accessor tests
    // =========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_simple_melee_for_melee_weapon_without_martial_property(): void
    {
        $meleeType = ItemType::firstOrCreate(
            ['code' => 'M'],
            ['name' => 'Melee Weapon']
        );

        $item = Item::factory()->create([
            'name' => 'Club',
            'item_type_id' => $meleeType->id,
        ]);

        // No properties attached - simple weapon

        $this->assertEquals('simple_melee', $item->proficiency_category);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_martial_melee_for_melee_weapon_with_martial_property(): void
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

        $this->assertEquals('martial_melee', $item->proficiency_category);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_simple_ranged_for_ranged_weapon_without_martial_property(): void
    {
        $rangedType = ItemType::firstOrCreate(
            ['code' => 'R'],
            ['name' => 'Ranged Weapon']
        );

        $item = Item::factory()->create([
            'name' => 'Shortbow',
            'item_type_id' => $rangedType->id,
        ]);

        // No martial property - simple weapon

        $this->assertEquals('simple_ranged', $item->proficiency_category);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_martial_ranged_for_ranged_weapon_with_martial_property(): void
    {
        $rangedType = ItemType::firstOrCreate(
            ['code' => 'R'],
            ['name' => 'Ranged Weapon']
        );

        $martialProperty = ItemProperty::firstOrCreate(
            ['code' => 'M'],
            ['name' => 'Martial']
        );

        $item = Item::factory()->create([
            'name' => 'Longbow',
            'item_type_id' => $rangedType->id,
        ]);

        $item->properties()->attach($martialProperty->id);

        $this->assertEquals('martial_ranged', $item->proficiency_category);
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

        $this->assertNull($item->proficiency_category);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_null_proficiency_category_for_armor(): void
    {
        $armorType = ItemType::firstOrCreate(
            ['code' => 'HA'],
            ['name' => 'Heavy Armor']
        );

        $item = Item::factory()->create([
            'name' => 'Plate Armor',
            'item_type_id' => $armorType->id,
        ]);

        $this->assertNull($item->proficiency_category);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_musical_instrument_for_items_with_instrument_detail(): void
    {
        $gearType = ItemType::firstOrCreate(
            ['code' => 'G'],
            ['name' => 'Adventuring Gear']
        );

        $item = Item::factory()->create([
            'name' => 'Lute',
            'item_type_id' => $gearType->id,
            'detail' => 'instrument',
        ]);

        $this->assertEquals('musical_instrument', $item->proficiency_category);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_musical_instrument_for_items_with_instrument_detail_and_rarity(): void
    {
        $gearType = ItemType::firstOrCreate(
            ['code' => 'G'],
            ['name' => 'Adventuring Gear']
        );

        $item = Item::factory()->create([
            'name' => 'Instrument of the Bards (Anstruth Harp)',
            'item_type_id' => $gearType->id,
            'detail' => 'instrument, very rare (requires attunement by a bard)',
            'is_magic' => true,
        ]);

        $this->assertEquals('musical_instrument', $item->proficiency_category);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_artisan_tools_for_items_with_artisan_tools_detail(): void
    {
        $gearType = ItemType::firstOrCreate(
            ['code' => 'G'],
            ['name' => 'Adventuring Gear']
        );

        $item = Item::factory()->create([
            'name' => "Smith's Tools",
            'item_type_id' => $gearType->id,
            'detail' => 'artisan tools',
        ]);

        $this->assertEquals('artisan_tools', $item->proficiency_category);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_gaming_set_for_items_with_gaming_set_detail(): void
    {
        $gearType = ItemType::firstOrCreate(
            ['code' => 'G'],
            ['name' => 'Adventuring Gear']
        );

        $item = Item::factory()->create([
            'name' => 'Dice Set',
            'item_type_id' => $gearType->id,
            'detail' => 'gaming set',
        ]);

        $this->assertEquals('gaming_set', $item->proficiency_category);
    }

    // =========================================================================
    // magic_bonus accessor tests
    // =========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_magic_bonus_from_weapon_attack_modifier(): void
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

        $this->assertEquals(2, $item->magic_bonus);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_magic_bonus_from_ac_magic_modifier_for_armor(): void
    {
        $armorType = ItemType::firstOrCreate(
            ['code' => 'HA'],
            ['name' => 'Heavy Armor']
        );

        $item = Item::factory()->create([
            'name' => 'Plate Armor +1',
            'item_type_id' => $armorType->id,
            'is_magic' => true,
        ]);

        Modifier::create([
            'reference_type' => Item::class,
            'reference_id' => $item->id,
            'modifier_category' => 'ac_magic',
            'value' => '1',
        ]);

        $this->assertEquals(1, $item->magic_bonus);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_magic_bonus_from_ac_magic_modifier_for_shield(): void
    {
        $shieldType = ItemType::firstOrCreate(
            ['code' => 'S'],
            ['name' => 'Shield']
        );

        $item = Item::factory()->create([
            'name' => 'Shield +3',
            'item_type_id' => $shieldType->id,
            'is_magic' => true,
        ]);

        Modifier::create([
            'reference_type' => Item::class,
            'reference_id' => $item->id,
            'modifier_category' => 'ac_magic',
            'value' => '3',
        ]);

        $this->assertEquals(3, $item->magic_bonus);
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

        $this->assertNull($item->magic_bonus);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_null_magic_bonus_for_magic_items_without_bonus_modifier(): void
    {
        $meleeType = ItemType::firstOrCreate(
            ['code' => 'M'],
            ['name' => 'Melee Weapon']
        );

        $item = Item::factory()->create([
            'name' => 'Flame Tongue',
            'item_type_id' => $meleeType->id,
            'is_magic' => true,
        ]);

        // No weapon_attack or ac_magic modifier

        $this->assertNull($item->magic_bonus);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_prefers_weapon_attack_over_ac_magic_for_magic_bonus(): void
    {
        // Edge case: if somehow both exist, weapon_attack takes precedence
        $meleeType = ItemType::firstOrCreate(
            ['code' => 'M'],
            ['name' => 'Melee Weapon']
        );

        $item = Item::factory()->create([
            'name' => 'Weird Magic Sword',
            'item_type_id' => $meleeType->id,
            'is_magic' => true,
        ]);

        Modifier::create([
            'reference_type' => Item::class,
            'reference_id' => $item->id,
            'modifier_category' => 'weapon_attack',
            'value' => '2',
        ]);

        Modifier::create([
            'reference_type' => Item::class,
            'reference_id' => $item->id,
            'modifier_category' => 'ac_magic',
            'value' => '1',
        ]);

        $this->assertEquals(2, $item->magic_bonus);
    }
}
