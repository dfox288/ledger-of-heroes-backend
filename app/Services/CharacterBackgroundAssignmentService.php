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
