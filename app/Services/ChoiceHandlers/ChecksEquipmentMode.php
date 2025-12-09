<?php

declare(strict_types=1);

namespace App\Services\ChoiceHandlers;

use App\Models\Character;

/**
 * Trait for checking equipment mode selection.
 *
 * Used by both EquipmentModeChoiceHandler and EquipmentChoiceHandler
 * to determine if gold mode was selected for starting equipment.
 */
trait ChecksEquipmentMode
{
    /**
     * Check if the character has selected gold mode for starting equipment.
     *
     * When gold mode is selected, equipment choices should be skipped.
     */
    protected function isGoldModeSelected(Character $character): bool
    {
        return $character->equipment_mode === 'gold';
    }

    /**
     * Check if the character has selected equipment mode for starting equipment.
     */
    protected function isEquipmentModeSelected(Character $character): bool
    {
        return $character->equipment_mode === 'equipment';
    }

    /**
     * Get the current equipment mode selection for a character.
     *
     * @return string|null 'equipment', 'gold', or null if not selected
     */
    protected function getEquipmentModeSelection(Character $character): ?string
    {
        return $character->equipment_mode;
    }
}
