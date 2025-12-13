<?php

namespace App\Enums;

/**
 * Equipment location slots for character inventory.
 *
 * Defines where an item is stored/equipped on the character.
 * Each body slot holds exactly one item (except backpack which is unlimited).
 *
 * @see https://github.com/dfox288/ledger-of-heroes/issues/582
 */
enum EquipmentLocation: string
{
    // Hand slots
    case MAIN_HAND = 'main_hand';
    case OFF_HAND = 'off_hand';

    // Body slots
    case HEAD = 'head';
    case NECK = 'neck';
    case CLOAK = 'cloak';
    case ARMOR = 'armor';
    case CLOTHES = 'clothes';
    case BELT = 'belt';
    case HANDS = 'hands';
    case FEET = 'feet';

    // Ring slots (two separate slots for rings)
    case RING_1 = 'ring_1';
    case RING_2 = 'ring_2';

    // Storage
    case BACKPACK = 'backpack';

    /**
     * Get all valid location values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get locations that represent equipped state (everything except backpack).
     *
     * @return array<string>
     */
    public static function equippedLocations(): array
    {
        return array_filter(
            self::values(),
            fn (string $value) => $value !== self::BACKPACK->value
        );
    }

    /**
     * Get locations that have a single slot limit (only one item allowed).
     * All equipped locations are single-slot except backpack.
     *
     * @return array<string>
     */
    public static function singleSlotLocations(): array
    {
        return self::equippedLocations();
    }

    /**
     * Check if this location represents an equipped state.
     */
    public function isEquipped(): bool
    {
        return $this !== self::BACKPACK;
    }

    /**
     * Check if this location has a single slot limit.
     */
    public function isSingleSlot(): bool
    {
        return $this !== self::BACKPACK;
    }

    /**
     * Get the maximum number of items allowed in this location.
     */
    public function maxSlots(): int
    {
        return match ($this) {
            self::BACKPACK => PHP_INT_MAX,
            default => 1,
        };
    }

    /**
     * Check if this location is a hand slot (main_hand or off_hand).
     */
    public function isHandSlot(): bool
    {
        return $this === self::MAIN_HAND || $this === self::OFF_HAND;
    }

    /**
     * Check if this location is a ring slot.
     */
    public function isRingSlot(): bool
    {
        return $this === self::RING_1 || $this === self::RING_2;
    }
}
