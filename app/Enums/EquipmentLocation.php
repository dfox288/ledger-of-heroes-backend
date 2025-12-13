<?php

namespace App\Enums;

/**
 * Equipment location slots for character inventory.
 *
 * Defines where an item is stored/equipped on the character.
 */
enum EquipmentLocation: string
{
    case MAIN_HAND = 'main_hand';
    case OFF_HAND = 'off_hand';
    case WORN = 'worn';
    case ATTUNED = 'attuned';
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
     * Get locations that represent equipped state.
     *
     * @return array<string>
     */
    public static function equippedLocations(): array
    {
        return [
            self::MAIN_HAND->value,
            self::OFF_HAND->value,
            self::WORN->value,
            self::ATTUNED->value,
        ];
    }

    /**
     * Get locations that have a single slot limit (only one item allowed).
     *
     * @return array<string>
     */
    public static function singleSlotLocations(): array
    {
        return [
            self::MAIN_HAND->value,
            self::OFF_HAND->value,
            self::WORN->value,
        ];
    }

    /**
     * Check if this location represents an equipped state.
     */
    public function isEquipped(): bool
    {
        return in_array($this->value, self::equippedLocations());
    }

    /**
     * Check if this location has a single slot limit.
     */
    public function isSingleSlot(): bool
    {
        return in_array($this->value, self::singleSlotLocations());
    }

    /**
     * Get the maximum number of items allowed in this location.
     */
    public function maxSlots(): int
    {
        return match ($this) {
            self::MAIN_HAND, self::OFF_HAND, self::WORN => 1,
            self::ATTUNED => 3,
            self::BACKPACK => PHP_INT_MAX,
        };
    }
}
