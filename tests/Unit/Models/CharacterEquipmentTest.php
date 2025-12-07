<?php

namespace Tests\Unit\Models;

use App\Enums\ItemTypeCode;
use App\Models\Character;
use App\Models\CharacterEquipment;
use App\Models\Item;
use App\Models\ItemType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterEquipmentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_belongs_to_character(): void
    {
        $character = Character::factory()->create();
        $item = Item::factory()->create();
        $equipment = CharacterEquipment::factory()->create([
            'character_id' => $character->id,
            'item_slug' => $item->full_slug,
        ]);

        $this->assertInstanceOf(Character::class, $equipment->character);
        $this->assertEquals($character->id, $equipment->character->id);
    }

    #[Test]
    public function it_belongs_to_item(): void
    {
        $item = Item::factory()->create();
        $equipment = CharacterEquipment::factory()->create(['item_slug' => $item->full_slug]);

        $this->assertInstanceOf(Item::class, $equipment->item);
        $this->assertEquals($item->id, $equipment->item->id);
    }

    #[Test]
    public function is_equipped_returns_true_when_equipped(): void
    {
        $item = Item::factory()->create();
        $equipment = CharacterEquipment::factory()->create([
            'equipped' => true,
            'item_slug' => $item->full_slug,
        ]);

        $this->assertTrue($equipment->isEquipped());
    }

    #[Test]
    public function is_equipped_returns_false_when_not_equipped(): void
    {
        $item = Item::factory()->create();
        $equipment = CharacterEquipment::factory()->create([
            'equipped' => false,
            'item_slug' => $item->full_slug,
        ]);

        $this->assertFalse($equipment->isEquipped());
    }

    #[Test]
    public function equip_sets_equipped_to_true_and_location_to_equipped(): void
    {
        $item = Item::factory()->create();
        $equipment = CharacterEquipment::factory()->create([
            'equipped' => false,
            'location' => 'backpack',
            'item_slug' => $item->full_slug,
        ]);

        $equipment->equip();

        $this->assertTrue($equipment->fresh()->equipped);
        $this->assertEquals('equipped', $equipment->fresh()->location);
    }

    #[Test]
    public function unequip_sets_equipped_to_false_and_location_to_backpack(): void
    {
        $item = Item::factory()->create();
        $equipment = CharacterEquipment::factory()->create([
            'equipped' => true,
            'location' => 'equipped',
            'item_slug' => $item->full_slug,
        ]);

        $equipment->unequip();

        $this->assertFalse($equipment->fresh()->equipped);
        $this->assertEquals('backpack', $equipment->fresh()->location);
    }

    #[Test]
    public function scope_equipped_filters_only_equipped_items(): void
    {
        $item = Item::factory()->create();
        CharacterEquipment::factory()->create(['equipped' => true, 'item_slug' => $item->full_slug]);
        CharacterEquipment::factory()->create(['equipped' => false, 'item_slug' => $item->full_slug]);
        CharacterEquipment::factory()->create(['equipped' => true, 'item_slug' => $item->full_slug]);

        $equipped = CharacterEquipment::equipped()->get();

        $this->assertCount(2, $equipped);
        $this->assertTrue($equipped->every(fn ($e) => $e->equipped));
    }

    #[Test]
    public function scope_armor_filters_armor_items(): void
    {
        $lightArmor = ItemType::firstOrCreate(
            ['code' => ItemTypeCode::LIGHT_ARMOR->value],
            ['name' => 'Light Armor', 'description' => 'Light armor']
        );
        $weapon = ItemType::firstOrCreate(
            ['code' => ItemTypeCode::MELEE_WEAPON->value],
            ['name' => 'Melee Weapon', 'description' => 'Melee weapon']
        );

        $armorItem = Item::factory()->create(['item_type_id' => $lightArmor->id]);
        $weaponItem = Item::factory()->create(['item_type_id' => $weapon->id]);

        CharacterEquipment::factory()->create(['item_slug' => $armorItem->full_slug]);
        CharacterEquipment::factory()->create(['item_slug' => $weaponItem->full_slug]);

        $armor = CharacterEquipment::armor()->get();

        $this->assertCount(1, $armor);
        $this->assertEquals($armorItem->full_slug, $armor->first()->item_slug);
    }

    #[Test]
    public function scope_shields_filters_shield_items(): void
    {
        $shield = ItemType::firstOrCreate(
            ['code' => ItemTypeCode::SHIELD->value],
            ['name' => 'Shield', 'description' => 'Shield']
        );
        $armor = ItemType::firstOrCreate(
            ['code' => ItemTypeCode::LIGHT_ARMOR->value],
            ['name' => 'Light Armor', 'description' => 'Light armor']
        );

        $shieldItem = Item::factory()->create(['item_type_id' => $shield->id]);
        $armorItem = Item::factory()->create(['item_type_id' => $armor->id]);

        CharacterEquipment::factory()->create(['item_slug' => $shieldItem->full_slug]);
        CharacterEquipment::factory()->create(['item_slug' => $armorItem->full_slug]);

        $shields = CharacterEquipment::shields()->get();

        $this->assertCount(1, $shields);
        $this->assertEquals($shieldItem->full_slug, $shields->first()->item_slug);
    }

    #[Test]
    public function scope_weapons_filters_weapon_items(): void
    {
        $melee = ItemType::firstOrCreate(
            ['code' => ItemTypeCode::MELEE_WEAPON->value],
            ['name' => 'Melee Weapon', 'description' => 'Melee weapon']
        );
        $ranged = ItemType::firstOrCreate(
            ['code' => ItemTypeCode::RANGED_WEAPON->value],
            ['name' => 'Ranged Weapon', 'description' => 'Ranged weapon']
        );
        $armor = ItemType::firstOrCreate(
            ['code' => ItemTypeCode::LIGHT_ARMOR->value],
            ['name' => 'Light Armor', 'description' => 'Light armor']
        );

        $meleeItem = Item::factory()->create(['item_type_id' => $melee->id]);
        $rangedItem = Item::factory()->create(['item_type_id' => $ranged->id]);
        $armorItem = Item::factory()->create(['item_type_id' => $armor->id]);

        CharacterEquipment::factory()->create(['item_slug' => $meleeItem->full_slug]);
        CharacterEquipment::factory()->create(['item_slug' => $rangedItem->full_slug]);
        CharacterEquipment::factory()->create(['item_slug' => $armorItem->full_slug]);

        $weapons = CharacterEquipment::weapons()->get();

        $this->assertCount(2, $weapons);
    }

    #[Test]
    public function is_armor_returns_true_for_armor(): void
    {
        $lightArmor = ItemType::firstOrCreate(
            ['code' => ItemTypeCode::LIGHT_ARMOR->value],
            ['name' => 'Light Armor', 'description' => 'Light armor']
        );
        $item = Item::factory()->create(['item_type_id' => $lightArmor->id]);
        $equipment = CharacterEquipment::factory()->create(['item_slug' => $item->full_slug]);

        $this->assertTrue($equipment->isArmor());
    }

    #[Test]
    public function is_armor_returns_false_for_non_armor(): void
    {
        $weapon = ItemType::firstOrCreate(
            ['code' => ItemTypeCode::MELEE_WEAPON->value],
            ['name' => 'Melee Weapon', 'description' => 'Melee weapon']
        );
        $item = Item::factory()->create(['item_type_id' => $weapon->id]);
        $equipment = CharacterEquipment::factory()->create(['item_slug' => $item->full_slug]);

        $this->assertFalse($equipment->isArmor());
    }

    #[Test]
    public function is_shield_returns_true_for_shield(): void
    {
        $shield = ItemType::firstOrCreate(
            ['code' => ItemTypeCode::SHIELD->value],
            ['name' => 'Shield', 'description' => 'Shield']
        );
        $item = Item::factory()->create(['item_type_id' => $shield->id]);
        $equipment = CharacterEquipment::factory()->create(['item_slug' => $item->full_slug]);

        $this->assertTrue($equipment->isShield());
    }

    #[Test]
    public function is_shield_returns_false_for_non_shield(): void
    {
        $armor = ItemType::firstOrCreate(
            ['code' => ItemTypeCode::LIGHT_ARMOR->value],
            ['name' => 'Light Armor', 'description' => 'Light armor']
        );
        $item = Item::factory()->create(['item_type_id' => $armor->id]);
        $equipment = CharacterEquipment::factory()->create(['item_slug' => $item->full_slug]);

        $this->assertFalse($equipment->isShield());
    }

    #[Test]
    public function is_weapon_returns_true_for_weapon(): void
    {
        $weapon = ItemType::firstOrCreate(
            ['code' => ItemTypeCode::MELEE_WEAPON->value],
            ['name' => 'Melee Weapon', 'description' => 'Melee weapon']
        );
        $item = Item::factory()->create(['item_type_id' => $weapon->id]);
        $equipment = CharacterEquipment::factory()->create(['item_slug' => $item->full_slug]);

        $this->assertTrue($equipment->isWeapon());
    }

    #[Test]
    public function is_weapon_returns_false_for_non_weapon(): void
    {
        $armor = ItemType::firstOrCreate(
            ['code' => ItemTypeCode::LIGHT_ARMOR->value],
            ['name' => 'Light Armor', 'description' => 'Light armor']
        );
        $item = Item::factory()->create(['item_type_id' => $armor->id]);
        $equipment = CharacterEquipment::factory()->create(['item_slug' => $item->full_slug]);

        $this->assertFalse($equipment->isWeapon());
    }

    #[Test]
    public function is_equippable_returns_true_for_equippable_items(): void
    {
        $weapon = ItemType::firstOrCreate(
            ['code' => ItemTypeCode::MELEE_WEAPON->value],
            ['name' => 'Melee Weapon', 'description' => 'Melee weapon']
        );
        $item = Item::factory()->create(['item_type_id' => $weapon->id]);
        $equipment = CharacterEquipment::factory()->create(['item_slug' => $item->full_slug]);

        $this->assertTrue($equipment->isEquippable());
    }

    #[Test]
    public function is_equippable_returns_false_for_non_equippable_items(): void
    {
        $gear = ItemType::firstOrCreate(
            ['code' => ItemTypeCode::ADVENTURING_GEAR->value],
            ['name' => 'Adventuring Gear', 'description' => 'Adventuring gear']
        );
        $item = Item::factory()->create(['item_type_id' => $gear->id]);
        $equipment = CharacterEquipment::factory()->create(['item_slug' => $item->full_slug]);

        $this->assertFalse($equipment->isEquippable());
    }

    #[Test]
    public function is_equippable_returns_false_for_custom_items(): void
    {
        $equipment = CharacterEquipment::factory()->create([
            'item_slug' => null,
            'custom_name' => 'Custom Item',
        ]);

        $this->assertFalse($equipment->isEquippable());
    }

    #[Test]
    public function is_custom_item_returns_true_when_no_item_slug_and_has_custom_name(): void
    {
        $equipment = CharacterEquipment::factory()->create([
            'item_slug' => null,
            'custom_name' => 'Custom Item',
        ]);

        $this->assertTrue($equipment->isCustomItem());
    }

    #[Test]
    public function is_custom_item_returns_false_when_has_item_slug(): void
    {
        $item = Item::factory()->create();
        $equipment = CharacterEquipment::factory()->create([
            'item_slug' => $item->full_slug,
            'custom_name' => null,
        ]);

        $this->assertFalse($equipment->isCustomItem());
    }

    #[Test]
    public function it_does_not_use_timestamps(): void
    {
        $this->assertFalse(CharacterEquipment::make()->usesTimestamps());
    }
}
