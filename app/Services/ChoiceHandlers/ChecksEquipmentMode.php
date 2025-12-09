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
     * The marker item slug used to track equipment mode selection.
     */
    protected const EQUIPMENT_MODE_MARKER = 'equipment_mode_marker';

    /**
     * Check if the character has selected gold mode for starting equipment.
     *
     * When gold mode is selected, equipment choices should be skipped.
     */
    protected function isGoldModeSelected(Character $character): bool
    {
        return $this->getEquipmentModeSelection($character) === 'gold';
    }

    /**
     * Get the current equipment mode selection for a character.
     *
     * @return string|null 'equipment', 'gold', or null if not selected
     */
    protected function getEquipmentModeSelection(Character $character): ?string
    {
        $metadata = $this->getEquipmentModeMetadata($character);

        return $metadata['equipment_mode'] ?? null;
    }

    /**
     * Get the full equipment mode metadata from the marker.
     *
     * @return array{equipment_mode?: string, source?: string, gold_amount?: int}|null
     */
    protected function getEquipmentModeMetadata(Character $character): ?array
    {
        // Load equipment if not already loaded
        if (! $character->relationLoaded('equipment')) {
            $character->load('equipment');
        }

        $marker = $character->equipment
            ->where('item_slug', self::EQUIPMENT_MODE_MARKER)
            ->first();

        if (! $marker || ! $marker->custom_description) {
            return null;
        }

        $metadata = json_decode($marker->custom_description, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $metadata;
    }
}
