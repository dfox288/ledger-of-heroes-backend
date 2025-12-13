<?php

namespace App\Services;

use App\Models\Character;

/**
 * Handles race assignment during character update.
 *
 * This service manages race assignment including HP recalculation
 * when race changes (due to CON modifier differences).
 */
class CharacterRaceAssignmentService
{
    public function __construct(
        private HitPointService $hitPointService,
        private CharacterProficiencyService $proficiencyService,
        private CharacterLanguageService $languageService,
        private CharacterFeatureService $featureService,
    ) {}

    /**
     * Check if race is being assigned or changed.
     */
    public function isRaceChanging(Character $character, array $validated): bool
    {
        if (! array_key_exists('race_slug', $validated)) {
            return false;
        }

        return $validated['race_slug'] !== $character->race_slug;
    }

    /**
     * Adjust HP when race changes (due to CON modifier differences).
     *
     * Must be called after the character's race_slug has been updated.
     */
    public function adjustHpForRaceChange(
        Character $character,
        ?string $previousRaceSlug,
        ?string $newRaceSlug
    ): void {
        if (! $character->usesCalculatedHp() || $character->total_level <= 0) {
            return;
        }

        $hpResult = $this->hitPointService->recalculateForRaceChange(
            $character,
            $previousRaceSlug,
            $newRaceSlug
        );

        if ($hpResult['adjustment'] !== 0) {
            $character->update([
                'max_hit_points' => $hpResult['new_max_hp'],
                'current_hit_points' => $hpResult['new_current_hp'],
            ]);
        }
    }

    /**
     * Grant fixed items from the race.
     *
     * Called after a race is assigned. The character's race relationship
     * should be reloaded before calling this to verify race exists.
     */
    public function grantRaceItems(Character $character): void
    {
        if (! $character->race) {
            return;
        }

        $this->proficiencyService->populateFromRace($character);
        $this->languageService->populateFromRace($character);
        $this->featureService->populateFromRace($character);
    }
}
