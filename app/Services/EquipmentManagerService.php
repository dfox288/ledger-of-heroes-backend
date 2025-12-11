<?php

namespace App\Services;

use App\Enums\ItemTypeCode;
use App\Exceptions\ItemNotEquippableException;
use App\Models\Character;
use App\Models\CharacterEquipment;
use App\Models\Item;
use Illuminate\Support\Facades\Log;

class EquipmentManagerService
{
    /**
     * Add item to character inventory.
     */
    public function addItem(Character $character, Item $item, int $quantity = 1): CharacterEquipment
    {
        // Check for existing unequipped stack
        $existing = $character->equipment()
            ->where('item_slug', $item->slug)
            ->where('equipped', false)
            ->first();

        if ($existing) {
            $existing->increment('quantity', $quantity);

            return $existing;
        }

        return $character->equipment()->create([
            'item_slug' => $item->slug,
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
     *
     * Important: This method checks if the equipment mode choice has been made.
     * - If not made yet: Does not populate (user must choose equipment vs gold first)
     * - If "gold" selected: Does not populate (user gets gold instead)
     * - If "equipment" selected: Populates fixed equipment
     */
    public function populateFromClass(Character $character): void
    {
        $primaryClass = $character->primary_class;
        if (! $primaryClass) {
            return;
        }

        // Check if the equipment mode choice exists and was resolved as "equipment"
        // If not yet resolved, or resolved as "gold", skip populating class equipment
        if (! $this->shouldPopulateClassEquipment($character)) {
            return;
        }

        $this->populateFromEntity($character, $primaryClass, 'class');
    }

    /**
     * Check if class equipment should be populated based on equipment mode selection
     * and equipment choice resolution status.
     *
     * Returns true only if:
     * - The class has no equipment choices (so no equipment_mode choice is presented), OR
     * - The equipment_mode choice was resolved with "equipment" selection AND
     *   all equipment choices have been resolved
     *
     * Returns false if:
     * - The equipment_mode choice exists but hasn't been resolved yet, OR
     * - The equipment_mode choice was resolved with "gold" selection, OR
     * - There are still unresolved equipment choices
     */
    private function shouldPopulateClassEquipment(Character $character): bool
    {
        $primaryClass = $character->primary_class;
        if (! $primaryClass) {
            return false;
        }

        // Check if class has starting wealth (required for equipment_mode choice)
        $hasStartingWealth = $primaryClass->starting_wealth !== null;

        // Check if class has equipment choices
        $equipmentChoices = $primaryClass->equipment()
            ->where('is_choice', true)
            ->get();
        $hasEquipmentChoices = $equipmentChoices->isNotEmpty();

        // If class has no starting wealth or no equipment choices, there's no equipment_mode choice
        // In this case, we can safely populate any fixed equipment
        if (! $hasStartingWealth || ! $hasEquipmentChoices) {
            return true;
        }

        // Class has both starting wealth and equipment choices, so equipment_mode choice exists
        // Check if it was resolved and what the selection was
        if ($character->equipment_mode === null) {
            // Choice hasn't been made yet - don't populate
            return false;
        }

        // If "gold" was selected, don't populate equipment
        if ($character->equipment_mode !== 'equipment') {
            return false;
        }

        // "equipment" was selected - now check if all equipment choices are resolved
        // Group equipment choices by choice_group
        $choiceGroups = $equipmentChoices->pluck('choice_group')->unique();

        // Check if each choice group has been resolved (equipment from that choice exists)
        foreach ($choiceGroups as $choiceGroup) {
            $hasSelection = $character->equipment()
                ->whereJsonContains('custom_description->source', 'class')
                ->whereJsonContains('custom_description->choice_group', $choiceGroup)
                ->exists();

            if (! $hasSelection) {
                // This choice group hasn't been resolved yet
                return false;
            }
        }

        // All checks passed - equipment mode is "equipment" and all choices resolved
        return true;
    }

    /**
     * Populate fixed equipment from the character's background.
     */
    public function populateFromBackground(Character $character): void
    {
        if (! $character->background_slug) {
            return;
        }

        $background = $character->background;
        if (! $background) {
            Log::warning('Character has background_slug but background relationship is null', [
                'character_id' => $character->id,
                'background_slug' => $character->background_slug,
            ]);

            return;
        }

        $this->populateFromEntity($character, $background, 'background');
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
     * Handles two types of equipment:
     * - Items with item_id: Creates entry with item_slug from the related Item
     * - Description-only items: Creates entry with item_slug=null and description in custom_description
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
            if ($entityItem->item) {
                // Standard item with item_id
                $this->createEquipmentFromItem($character, $entityItem, $source);
            } elseif ($entityItem->description) {
                // Description-only item (e.g., "holy symbol (a gift...)")
                $this->createEquipmentFromDescription($character, $entityItem, $source);
            } else {
                Log::warning('EntityItem has neither item nor description', [
                    'entity_item_id' => $entityItem->id,
                    'entity_type' => get_class($entity),
                    'entity_id' => $entity->id,
                ]);
            }
        }

        // Refresh the relationship
        $character->load('equipment');
    }

    /**
     * Create equipment entry from an EntityItem with a linked Item.
     */
    private function createEquipmentFromItem(Character $character, $entityItem, string $source): void
    {
        $itemSlug = $entityItem->item->slug;

        // Skip if this item already exists from this source
        $exists = $character->equipment()
            ->where('item_slug', $itemSlug)
            ->whereJsonContains('custom_description->source', $source)
            ->exists();

        if ($exists) {
            return;
        }

        CharacterEquipment::create([
            'character_id' => $character->id,
            'item_slug' => $itemSlug,
            'quantity' => $entityItem->quantity ?? 1,
            'equipped' => false,
            'custom_description' => json_encode(['source' => $source]),
        ]);
    }

    /**
     * Create equipment entry from a description-only EntityItem.
     *
     * These are items like "holy symbol (a gift...)" or "5 sticks of incense"
     * that don't have a corresponding Item in the database.
     */
    private function createEquipmentFromDescription(Character $character, $entityItem, string $source): void
    {
        $description = $entityItem->description;

        // Skip if this description-only item already exists from this source
        $exists = $character->equipment()
            ->whereNull('item_slug')
            ->whereJsonContains('custom_description->source', $source)
            ->whereJsonContains('custom_description->description', $description)
            ->exists();

        if ($exists) {
            return;
        }

        CharacterEquipment::create([
            'character_id' => $character->id,
            'item_slug' => null,
            'custom_name' => $description,
            'quantity' => $entityItem->quantity ?? 1,
            'equipped' => false,
            'custom_description' => json_encode([
                'source' => $source,
                'description' => $description,
            ]),
        ]);
    }
}
