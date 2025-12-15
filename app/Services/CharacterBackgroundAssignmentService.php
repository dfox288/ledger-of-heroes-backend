<?php

namespace App\Services;

use App\Models\Character;

/**
 * Handles background assignment during character update.
 *
 * This service manages background assignment including
 * granting fixed equipment, proficiencies, languages, and features.
 */
class CharacterBackgroundAssignmentService
{
    public function __construct(
        private EquipmentManagerService $equipmentService,
        private CharacterProficiencyService $proficiencyService,
        private CharacterLanguageService $languageService,
        private CharacterFeatureService $featureService,
    ) {}

    /**
     * Check if background is being assigned or changed.
     */
    public function isBackgroundChanging(Character $character, ?string $newBackgroundSlug): bool
    {
        if (! $newBackgroundSlug) {
            return false;
        }

        return $newBackgroundSlug !== $character->background_slug;
    }

    /**
     * Clear old background data before assigning a new background.
     *
     * When background changes, old background-sourced data must be cleared:
     * - Equipment from old background
     * - Proficiencies from old background
     * - Languages from old background (both fixed and choice-based)
     * - Features from old background
     *
     * This is called BEFORE updating the background_slug on the character.
     */
    public function clearOldBackgroundData(Character $character): void
    {
        if (! $character->background_slug) {
            return;
        }

        // Clear background equipment
        $character->equipment()
            ->whereJsonContains('custom_description->source', 'background')
            ->delete();

        // Clear background proficiencies (both fixed and choice-based)
        $this->proficiencyService->clearProficiencies($character, 'background');

        // Clear background languages (both fixed and choice-based)
        $character->languages()->where('source', 'background')->delete();

        // Clear background features
        $character->features()->where('source', 'background')->delete();
    }

    /**
     * Grant fixed items from the background.
     *
     * Called after a background is assigned. The character's background
     * relationship should be reloaded before calling this to verify it exists.
     */
    public function grantBackgroundItems(Character $character): void
    {
        if (! $character->background) {
            return;
        }

        $this->equipmentService->populateFromBackground($character);
        $this->proficiencyService->populateFromBackground($character);
        $this->languageService->populateFromBackground($character);
        $this->featureService->populateFromBackground($character);
    }
}
