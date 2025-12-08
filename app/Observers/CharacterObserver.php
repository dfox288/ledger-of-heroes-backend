<?php

namespace App\Observers;

use App\Models\Character;
use App\Services\CharacterLanguageService;
use App\Services\HitPointService;

class CharacterObserver
{
    public function __construct(
        protected CharacterLanguageService $languageService,
        protected HitPointService $hitPointService
    ) {}

    /**
     * Handle the Character "updating" event.
     *
     * Recalculates HP when constitution changes for characters using calculated HP.
     */
    public function updating(Character $character): void
    {
        // Only process if CON is being changed
        if (! $character->isDirty('constitution')) {
            return;
        }

        // Only recalculate for characters using calculated HP
        if (! $character->usesCalculatedHp()) {
            return;
        }

        $oldCon = $character->getOriginal('constitution');
        $newCon = $character->constitution;

        // Skip if either value is null
        if ($oldCon === null || $newCon === null) {
            return;
        }

        $result = $this->hitPointService->recalculateForConChange(
            $character,
            $oldCon,
            $newCon
        );

        // Only update if there's an actual adjustment
        if ($result['adjustment'] !== 0) {
            $character->max_hit_points = $result['new_max_hp'];
            $character->current_hit_points = $result['new_current_hp'];
        }
    }

    /**
     * Handle the Character "updated" event.
     */
    public function updated(Character $character): void
    {
        // Check if race_slug changed and is not null
        if ($character->wasChanged('race_slug') && $character->race_slug) {
            $this->languageService->populateFromRace($character);
        }

        // Check if background_slug changed and is not null
        if ($character->wasChanged('background_slug') && $character->background_slug) {
            $this->languageService->populateFromBackground($character);
        }
    }
}
