<?php

namespace App\Enums;

/**
 * Item type codes from the item_types table.
 *
 * Used to identify armor, weapons, and other equipment categories
 * without relying on hardcoded database IDs.
 */
enum ItemTypeCode: string
{
    case LIGHT_ARMOR = 'LA';
    case MEDIUM_ARMOR = 'MA';
    case HEAVY_ARMOR = 'HA';
    case SHIELD = 'S';
    case MELEE_WEAPON = 'M';
    case RANGED_WEAPON = 'R';
    case AMMUNITION = 'A';
    case ADVENTURING_GEAR = 'G';
    case POTION = 'P';
    case RING = 'RG';
    case ROD = 'RD';
    case SCROLL = 'SC';
    case STAFF = 'ST';
    case WAND = 'WD';
    case WONDROUS_ITEM = 'W';
    case TRADE_GOODS = '$';

    /**
     * Get all armor type codes.
     *
     * @return array<string>
     */
    public static function armorCodes(): array
    {
        return [
            self::LIGHT_ARMOR->value,
            self::MEDIUM_ARMOR->value,
            self::HEAVY_ARMOR->value,
        ];
    }

    /**
     * Get all weapon type codes.
     *
     * @return array<string>
     */
    public static function weaponCodes(): array
    {
        return [
            self::MELEE_WEAPON->value,
            self::RANGED_WEAPON->value,
        ];
    }

    /**
     * Get all equippable item type codes.
     *
     * @return array<string>
     */
    public static function equippableCodes(): array
    {
        return [
            ...self::armorCodes(),
            self::SHIELD->value,
            ...self::weaponCodes(),
        ];
    }

    /**
     * Check if this type is armor (light, medium, or heavy).
     */
    public function isArmor(): bool
    {
        return in_array($this->value, self::armorCodes());
    }

    /**
     * Check if this type is a weapon.
     */
    public function isWeapon(): bool
    {
        return in_array($this->value, self::weaponCodes());
    }

    /**
     * Check if this type is equippable.
     */
    public function isEquippable(): bool
    {
        return in_array($this->value, self::equippableCodes());
    }
}
