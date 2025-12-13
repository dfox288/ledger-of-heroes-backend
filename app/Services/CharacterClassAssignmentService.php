<?php

namespace App\Services;

use App\Models\Character;
use App\Models\CharacterClassPivot;

/**
 * Handles class assignment during character update.
 *
 * This service manages the simpler class assignment logic used during
 * character creation/update, distinct from AddClassService which handles
 * full multiclass validation and grants.
 */
class CharacterClassAssignmentService
{
    public function __construct(
        private EquipmentManagerService $equipmentService,
        private CharacterProficiencyService $proficiencyService,
        private CharacterLanguageService $languageService,
        private CharacterFeatureService $featureService,
    ) {}

    /**
     * Assign a class to a character if not already present.
     *
     * Returns true if a new primary class was assigned (triggering fixed grants).
     */
    public function assignClass(Character $character, string $classSlug): bool
    {
        // Lock the character's class rows to prevent concurrent modifications
        $existingClasses = $character->characterClasses()->lockForUpdate()->get();

        // Only add if character doesn't already have this class
        if ($existingClasses->where('class_slug', $classSlug)->first()) {
            return false;
        }

        $isPrimary = $existingClasses->isEmpty();
        $order = ($existingClasses->max('order') ?? 0) + 1;

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $classSlug,
            'level' => 1,
            'is_primary' => $isPrimary,
            'order' => $order,
            'hit_dice_spent' => 0,
        ]);

        return $isPrimary;
    }

    /**
     * Update the level of the character's primary class.
     */
    public function updatePrimaryClassLevel(Character $character, int $level): bool
    {
        $primaryClass = $character->characterClasses()->where('is_primary', true)->first();

        if (! $primaryClass) {
            return false;
        }

        $primaryClass->update(['level' => $level]);

        return true;
    }

    /**
     * Grant fixed items from the primary class.
     *
     * Called after a primary class is assigned.
     */
    public function grantPrimaryClassItems(Character $character): void
    {
        $this->equipmentService->populateFromClass($character);
        $this->proficiencyService->populateFromClass($character);
        $this->languageService->populateFromClass($character);
        $this->featureService->populateFromClass($character);
    }
}
