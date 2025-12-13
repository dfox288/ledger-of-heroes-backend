<?php

namespace App\Enums;

/**
 * Groups for organizing equipment in character inventory.
 *
 * Maps item types to display groups for frontend organization.
 */
enum ItemGroup: string
{
    case WEAPONS = 'Weapons';
    case ARMOR = 'Armor';
    case CONSUMABLES = 'Consumables';
    case MAGIC_ITEMS = 'Magic Items';
    case GEAR = 'Gear';
    case MISCELLANEOUS = 'Miscellaneous';

    /**
     * Get the group for an item type name.
     */
    public static function fromItemType(?string $itemTypeName): self
    {
        if ($itemTypeName === null) {
            return self::MISCELLANEOUS;
        }

        return match ($itemTypeName) {
            // Weapons
            'Melee Weapon', 'Ranged Weapon', 'Ammunition' => self::WEAPONS,

            // Armor
            'Light Armor', 'Medium Armor', 'Heavy Armor', 'Shield' => self::ARMOR,

            // Consumables
            'Potion', 'Scroll' => self::CONSUMABLES,

            // Magic Items (wands, rods, rings, staves, wondrous)
            'Wand', 'Rod', 'Ring', 'Staff', 'Wondrous Item' => self::MAGIC_ITEMS,

            // Gear (tools, adventuring gear)
            'Adventuring Gear', 'Trade Goods' => self::GEAR,

            // Fallback
            default => self::MISCELLANEOUS,
        };
    }
}
