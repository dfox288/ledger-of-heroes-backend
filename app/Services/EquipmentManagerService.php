<?php

namespace App\Services;

use App\Models\Character;
use App\Models\CharacterEquipment;
use App\Models\Item;

class EquipmentManagerService
{
    /**
     * Item type codes for armor and shield categories.
     */
    private const ARMOR_TYPE_CODES = ['LA', 'MA', 'HA'];

    private const SHIELD_TYPE_CODE = 'S';

    /**
     * Add item to character inventory.
     */
    public function addItem(Character $character, Item $item, int $quantity = 1): CharacterEquipment
    {
        // Check for existing unequipped stack
        $existing = $character->equipment()
            ->where('item_id', $item->id)
            ->where('equipped', false)
            ->first();

        if ($existing) {
            $existing->increment('quantity', $quantity);

            return $existing;
        }

        return $character->equipment()->create([
            'item_id' => $item->id,
            'quantity' => $quantity,
            'equipped' => false,
            'location' => 'backpack',
        ]);
    }

    /**
     * Remove item from inventory.
     */
    public function removeItem(CharacterEquipment $equipment, ?int $quantity = null): void
    {
        if ($quantity === null || $quantity >= $equipment->quantity) {
            $equipment->delete();
        } else {
            $equipment->decrement('quantity', $quantity);
        }
    }

    /**
     * Equip an item.
     */
    public function equipItem(CharacterEquipment $equipment): void
    {
        $item = $equipment->item;
        $item->loadMissing('itemType');

        // Unequip conflicting items
        if ($this->isArmor($item)) {
            $this->unequipCurrentArmor($equipment->character);
        } elseif ($this->isShield($item)) {
            $this->unequipCurrentShield($equipment->character);
        }

        $equipment->update([
            'equipped' => true,
            'location' => 'equipped',
        ]);
    }

    /**
     * Unequip an item.
     */
    public function unequipItem(CharacterEquipment $equipment): void
    {
        $equipment->update([
            'equipped' => false,
            'location' => 'backpack',
        ]);
    }

    private function unequipCurrentArmor(Character $character): void
    {
        $character->equipment()
            ->where('equipped', true)
            ->whereHas('item.itemType', fn ($q) => $q->whereIn('code', self::ARMOR_TYPE_CODES))
            ->update(['equipped' => false, 'location' => 'backpack']);
    }

    private function unequipCurrentShield(Character $character): void
    {
        $character->equipment()
            ->where('equipped', true)
            ->whereHas('item.itemType', fn ($q) => $q->where('code', self::SHIELD_TYPE_CODE))
            ->update(['equipped' => false, 'location' => 'backpack']);
    }

    private function isArmor(Item $item): bool
    {
        return in_array($item->itemType?->code, self::ARMOR_TYPE_CODES);
    }

    private function isShield(Item $item): bool
    {
        return $item->itemType?->code === self::SHIELD_TYPE_CODE;
    }
}
