<?php

namespace Tests\Unit\Enums;

use App\Enums\ItemTypeCode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ItemTypeCodeTest extends TestCase
{
    #[Test]
    public function it_has_correct_values(): void
    {
        $this->assertSame('LA', ItemTypeCode::LIGHT_ARMOR->value);
        $this->assertSame('MA', ItemTypeCode::MEDIUM_ARMOR->value);
        $this->assertSame('HA', ItemTypeCode::HEAVY_ARMOR->value);
        $this->assertSame('S', ItemTypeCode::SHIELD->value);
        $this->assertSame('M', ItemTypeCode::MELEE_WEAPON->value);
        $this->assertSame('R', ItemTypeCode::RANGED_WEAPON->value);
        $this->assertSame('A', ItemTypeCode::AMMUNITION->value);
        $this->assertSame('G', ItemTypeCode::ADVENTURING_GEAR->value);
        $this->assertSame('P', ItemTypeCode::POTION->value);
        $this->assertSame('RG', ItemTypeCode::RING->value);
        $this->assertSame('RD', ItemTypeCode::ROD->value);
        $this->assertSame('SC', ItemTypeCode::SCROLL->value);
        $this->assertSame('ST', ItemTypeCode::STAFF->value);
        $this->assertSame('WD', ItemTypeCode::WAND->value);
        $this->assertSame('W', ItemTypeCode::WONDROUS_ITEM->value);
        $this->assertSame('$', ItemTypeCode::TRADE_GOODS->value);
    }

    #[Test]
    public function it_can_be_created_from_string(): void
    {
        $this->assertSame(ItemTypeCode::LIGHT_ARMOR, ItemTypeCode::from('LA'));
        $this->assertSame(ItemTypeCode::MELEE_WEAPON, ItemTypeCode::from('M'));
        $this->assertSame(ItemTypeCode::TRADE_GOODS, ItemTypeCode::from('$'));
    }

    #[Test]
    public function it_returns_null_for_invalid_string_with_try_from(): void
    {
        $this->assertNull(ItemTypeCode::tryFrom('invalid'));
        $this->assertNull(ItemTypeCode::tryFrom('X'));
    }

    #[Test]
    public function armor_codes_returns_all_armor_types(): void
    {
        $armorCodes = ItemTypeCode::armorCodes();

        $this->assertCount(3, $armorCodes);
        $this->assertContains('LA', $armorCodes);
        $this->assertContains('MA', $armorCodes);
        $this->assertContains('HA', $armorCodes);
    }

    #[Test]
    public function weapon_codes_returns_all_weapon_types(): void
    {
        $weaponCodes = ItemTypeCode::weaponCodes();

        $this->assertCount(2, $weaponCodes);
        $this->assertContains('M', $weaponCodes);
        $this->assertContains('R', $weaponCodes);
    }

    #[Test]
    public function equippable_codes_returns_armor_shield_and_weapons(): void
    {
        $equippableCodes = ItemTypeCode::equippableCodes();

        $this->assertCount(6, $equippableCodes);
        $this->assertContains('LA', $equippableCodes);
        $this->assertContains('MA', $equippableCodes);
        $this->assertContains('HA', $equippableCodes);
        $this->assertContains('S', $equippableCodes);
        $this->assertContains('M', $equippableCodes);
        $this->assertContains('R', $equippableCodes);
    }

    #[Test]
    public function is_armor_returns_true_for_armor_types(): void
    {
        $this->assertTrue(ItemTypeCode::LIGHT_ARMOR->isArmor());
        $this->assertTrue(ItemTypeCode::MEDIUM_ARMOR->isArmor());
        $this->assertTrue(ItemTypeCode::HEAVY_ARMOR->isArmor());
    }

    #[Test]
    public function is_armor_returns_false_for_non_armor_types(): void
    {
        $this->assertFalse(ItemTypeCode::SHIELD->isArmor());
        $this->assertFalse(ItemTypeCode::MELEE_WEAPON->isArmor());
        $this->assertFalse(ItemTypeCode::RANGED_WEAPON->isArmor());
        $this->assertFalse(ItemTypeCode::POTION->isArmor());
        $this->assertFalse(ItemTypeCode::WONDROUS_ITEM->isArmor());
    }

    #[Test]
    public function is_weapon_returns_true_for_weapon_types(): void
    {
        $this->assertTrue(ItemTypeCode::MELEE_WEAPON->isWeapon());
        $this->assertTrue(ItemTypeCode::RANGED_WEAPON->isWeapon());
    }

    #[Test]
    public function is_weapon_returns_false_for_non_weapon_types(): void
    {
        $this->assertFalse(ItemTypeCode::LIGHT_ARMOR->isWeapon());
        $this->assertFalse(ItemTypeCode::SHIELD->isWeapon());
        $this->assertFalse(ItemTypeCode::AMMUNITION->isWeapon());
        $this->assertFalse(ItemTypeCode::POTION->isWeapon());
        $this->assertFalse(ItemTypeCode::STAFF->isWeapon());
    }

    #[Test]
    public function is_equippable_returns_true_for_equippable_types(): void
    {
        $this->assertTrue(ItemTypeCode::LIGHT_ARMOR->isEquippable());
        $this->assertTrue(ItemTypeCode::MEDIUM_ARMOR->isEquippable());
        $this->assertTrue(ItemTypeCode::HEAVY_ARMOR->isEquippable());
        $this->assertTrue(ItemTypeCode::SHIELD->isEquippable());
        $this->assertTrue(ItemTypeCode::MELEE_WEAPON->isEquippable());
        $this->assertTrue(ItemTypeCode::RANGED_WEAPON->isEquippable());
    }

    #[Test]
    public function is_equippable_returns_false_for_non_equippable_types(): void
    {
        $this->assertFalse(ItemTypeCode::AMMUNITION->isEquippable());
        $this->assertFalse(ItemTypeCode::ADVENTURING_GEAR->isEquippable());
        $this->assertFalse(ItemTypeCode::POTION->isEquippable());
        $this->assertFalse(ItemTypeCode::RING->isEquippable());
        $this->assertFalse(ItemTypeCode::ROD->isEquippable());
        $this->assertFalse(ItemTypeCode::SCROLL->isEquippable());
        $this->assertFalse(ItemTypeCode::STAFF->isEquippable());
        $this->assertFalse(ItemTypeCode::WAND->isEquippable());
        $this->assertFalse(ItemTypeCode::WONDROUS_ITEM->isEquippable());
        $this->assertFalse(ItemTypeCode::TRADE_GOODS->isEquippable());
    }

    #[Test]
    public function shield_is_equippable_but_not_armor_or_weapon(): void
    {
        $this->assertTrue(ItemTypeCode::SHIELD->isEquippable());
        $this->assertFalse(ItemTypeCode::SHIELD->isArmor());
        $this->assertFalse(ItemTypeCode::SHIELD->isWeapon());
    }

    #[Test]
    public function armor_codes_do_not_include_shield(): void
    {
        $armorCodes = ItemTypeCode::armorCodes();
        $this->assertNotContains('S', $armorCodes);
    }

    #[Test]
    public function all_armor_types_are_equippable(): void
    {
        foreach (ItemTypeCode::armorCodes() as $code) {
            $itemType = ItemTypeCode::from($code);
            $this->assertTrue($itemType->isEquippable(), "Armor type {$code} should be equippable");
        }
    }

    #[Test]
    public function all_weapon_types_are_equippable(): void
    {
        foreach (ItemTypeCode::weaponCodes() as $code) {
            $itemType = ItemTypeCode::from($code);
            $this->assertTrue($itemType->isEquippable(), "Weapon type {$code} should be equippable");
        }
    }
}
