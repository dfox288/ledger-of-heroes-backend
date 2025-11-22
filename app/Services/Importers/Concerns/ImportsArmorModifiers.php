<?php

namespace App\Services\Importers\Concerns;

use App\Models\Item;

/**
 * Trait for importing AC (Armor Class) modifiers for armor and shield items.
 *
 * Handles the creation of structured AC modifiers that represent:
 * - Base armor AC with DEX modifier rules (ac_base)
 * - Shield AC bonuses (ac_bonus)
 * - Magic enchantments (ac_magic - handled separately in XML parser)
 *
 * D&D 5e AC System:
 * - Armor provides base AC with different DEX modifier rules
 * - Shields provide additive AC bonus
 * - Magic items provide additional bonuses
 *
 * Used by: ItemImporter
 */
trait ImportsArmorModifiers
{
    /**
     * Import AC modifier for an armor or shield item.
     *
     * Determines the correct modifier category and condition based on item type,
     * then creates the modifier if it doesn't already exist.
     *
     * @param  Item  $item  The item entity
     * @param  string  $typeCode  Item type code (S, LA, MA, HA)
     * @param  int  $armorClass  AC value from item data
     */
    protected function importArmorAcModifier(Item $item, string $typeCode, int $armorClass): void
    {
        // Skip if no AC value
        if (empty($armorClass) || $armorClass <= 0) {
            return;
        }

        // Determine modifier category and DEX modifier rule based on type
        [$category, $condition] = $this->getAcModifierTypeAndCondition($typeCode);

        // Skip if not an armor/shield type
        if (! $category) {
            return;
        }

        // Check if modifier already exists (avoid duplicates on re-import)
        if ($this->hasAcModifier($item, $category, $armorClass)) {
            return;
        }

        // Create the AC modifier
        $item->modifiers()->create([
            'modifier_category' => $category,
            'value' => $armorClass,
            'condition' => $condition,
        ]);
    }

    /**
     * Get modifier category and condition for an item type.
     *
     * Maps D&D armor types to modifier structure:
     * - Shields: ac_bonus (always additive, no DEX rules)
     * - Light Armor: ac_base with full DEX modifier
     * - Medium Armor: ac_base with DEX modifier capped at +2
     * - Heavy Armor: ac_base with no DEX modifier
     *
     * @param  string  $typeCode  Item type code
     * @return array [category, condition] or [null, null] if not armor/shield
     */
    private function getAcModifierTypeAndCondition(string $typeCode): array
    {
        return match ($typeCode) {
            'S' => ['ac_bonus', null],                      // Shield
            'LA' => ['ac_base', 'dex_modifier: full'],     // Light armor
            'MA' => ['ac_base', 'dex_modifier: max_2'],    // Medium armor
            'HA' => ['ac_base', 'dex_modifier: none'],     // Heavy armor
            default => [null, null],                        // Not armor/shield
        };
    }

    /**
     * Check if an item already has a specific AC modifier.
     *
     * Prevents duplicate modifiers on re-import.
     *
     * @param  Item  $item  The item entity
     * @param  string  $category  Modifier category (ac_base, ac_bonus)
     * @param  int  $value  AC value
     * @return bool True if modifier exists
     */
    private function hasAcModifier(Item $item, string $category, int $value): bool
    {
        return $item->modifiers()
            ->where('modifier_category', $category)
            ->where('value', $value)
            ->exists();
    }
}
