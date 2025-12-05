<?php

namespace App\Observers;

use App\Models\Character;
use App\Services\CharacterLanguageService;

class CharacterObserver
{
    public function __construct(
        protected CharacterLanguageService $languageService
    ) {}

    /**
     * Handle the Character "updated" event.
     */
    public function updated(Character $character): void
    {
        // Check if race_id changed and is not null
        if ($character->wasChanged('race_id') && $character->race_id) {
            $this->languageService->populateFromRace($character);
        }

        // Check if background_id changed and is not null
        if ($character->wasChanged('background_id') && $character->background_id) {
            $this->languageService->populateFromBackground($character);
        }
    }
}
