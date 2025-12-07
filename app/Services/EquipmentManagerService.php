<?php

namespace App\Services;

use App\Enums\ItemTypeCode;
use App\Exceptions\ItemNotEquippableException;
use App\Models\Character;
use App\Models\CharacterEquipment;
use App\Models\Item;

class EquipmentManagerService
{
    /**
     * Add item to character inventory.
     */
    public function addItem(Character $character, Item $item, int $quantity = 1): CharacterEquipment
    {
        // Check for existing unequipped stack
        $existing = $character->equipment()
            ->where('item_slug', $item->full_slug)
            ->where('equipped', false)
            ->first();

        if ($existing) {
            $existing->increment('quantity', $quantity);

            return $existing;
        }

        return $character->equipment()->create([
            'item_slug' => $item->full_slug,
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
     *
     * @throws ItemNotEquippableException
     */
    public function equipItem(CharacterEquipment $equipment): void
    {
        $item = $equipment->item;
        $item->loadMissing('itemType');

        // Validate item can be equipped
        if (! $this->isEquippable($item)) {
            throw new ItemNotEquippableException($item);
        }

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
            ->whereHas('item.itemType', fn ($q) => $q->whereIn('code', ItemTypeCode::armorCodes()))
            ->update(['equipped' => false, 'location' => 'backpack']);
    }

    private function unequipCurrentShield(Character $character): void
    {
        $character->equipment()
            ->where('equipped', true)
            ->whereHas('item.itemType', fn ($q) => $q->where('code', ItemTypeCode::SHIELD->value))
            ->update(['equipped' => false, 'location' => 'backpack']);
    }

    private function isArmor(Item $item): bool
    {
        return in_array($item->itemType?->code, ItemTypeCode::armorCodes());
    }

    private function isShield(Item $item): bool
    {
        return $item->itemType?->code === ItemTypeCode::SHIELD->value;
    }

    private function isEquippable(Item $item): bool
    {
        return in_array($item->itemType?->code, ItemTypeCode::equippableCodes());
    }

    /**
     * Populate fixed (non-choice) equipment from the character's primary class.
     *
     * Only the primary class grants starting equipment.
     * Multiclass additions do not grant additional equipment.
     */
    public function populateFromClass(Character $character): void
    {
        $primaryClass = $character->primary_class;
        if (! $primaryClass) {
            return;
        }

        $this->populateFromEntity($character, $primaryClass, 'class');
    }

    /**
     * Populate fixed equipment from the character's background.
     */
    public function populateFromBackground(Character $character): void
    {
        if (! $character->background_slug) {
            return;
        }

        $this->populateFromEntity($character, $character->background, 'background');
    }

    /**
     * Populate all fixed equipment from class and background.
     */
    public function populateAll(Character $character): void
    {
        $this->populateFromClass($character);
        $this->populateFromBackground($character);
    }

    /**
     * Populate fixed equipment from an entity (class or background).
     *
     * @param  Character  $character  The character to populate
     * @param  mixed  $entity  The source entity (CharacterClass or Background)
     * @param  string  $source  The source identifier ('class' or 'background')
     */
    protected function populateFromEntity(Character $character, $entity, string $source): void
    {
        // Get fixed equipment (is_choice = false) with item relationship
        $fixedEquipment = $entity->equipment()
            ->where('is_choice', false)
            ->with('item')
            ->get();

        foreach ($fixedEquipment as $entityItem) {
            if (! $entityItem->item) {
                continue;
            }

            $itemSlug = $entityItem->item->full_slug;

            // Skip if this item already exists from this source
            $exists = $character->equipment()
                ->where('item_slug', $itemSlug)
                ->where('custom_description', json_encode(['source' => $source]))
                ->exists();

            if ($exists) {
                continue;
            }

            CharacterEquipment::create([
                'character_id' => $character->id,
                'item_slug' => $itemSlug,
                'quantity' => $entityItem->quantity ?? 1,
                'equipped' => false,
                'custom_description' => json_encode(['source' => $source]),
            ]);
        }

        // Refresh the relationship
        $character->load('equipment');
    }
}
