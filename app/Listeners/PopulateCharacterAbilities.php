<?php

namespace App\Listeners;

use App\Events\CharacterUpdated;
use App\Services\CharacterFeatureService;
use App\Services\CharacterProficiencyService;

class PopulateCharacterAbilities
{
    public function __construct(
        private CharacterFeatureService $featureService,
        private CharacterProficiencyService $proficiencyService
    ) {}

    /**
     * Handle the CharacterUpdated event.
     *
     * When class_id, race_id, or background_id changes, we need to:
     * 1. Clear features/proficiencies from the old source
     * 2. Re-populate from the new source
     */
    public function handle(CharacterUpdated $event): void
    {
        $character = $event->character;
        $original = $character->getOriginal();

        // Check if class changed
        if ($this->hasChanged($original, $character, 'class_id')) {
            $this->handleClassChange($character, $original['class_id'] ?? null);
        }

        // Check if race changed
        if ($this->hasChanged($original, $character, 'race_id')) {
            $this->handleRaceChange($character, $original['race_id'] ?? null);
        }

        // Check if background changed
        if ($this->hasChanged($original, $character, 'background_id')) {
            $this->handleBackgroundChange($character, $original['background_id'] ?? null);
        }

        // Check if level changed (need to add new class features)
        if ($this->hasChanged($original, $character, 'level')) {
            $this->handleLevelChange($character);
        }
    }

    /**
     * Check if a specific attribute has changed.
     */
    private function hasChanged(array $original, $character, string $attribute): bool
    {
        $originalValue = $original[$attribute] ?? null;
        $newValue = $character->{$attribute};

        return $originalValue !== $newValue;
    }

    /**
     * Handle class change - clear old class proficiencies/features and populate new ones.
     */
    private function handleClassChange($character, ?int $oldClassId): void
    {
        // Only clear/repopulate if they had a previous class
        // This avoids clearing on initial character creation
        if ($oldClassId !== null) {
            $this->proficiencyService->clearProficiencies($character, 'class');
            $this->featureService->clearFeatures($character, 'class');
        }

        // Populate from new class
        if ($character->class_id) {
            $this->proficiencyService->populateFromClass($character);
            $this->featureService->populateFromClass($character);
        }
    }

    /**
     * Handle race change - clear old race proficiencies/features and populate new ones.
     */
    private function handleRaceChange($character, ?int $oldRaceId): void
    {
        if ($oldRaceId !== null) {
            $this->proficiencyService->clearProficiencies($character, 'race');
            $this->featureService->clearFeatures($character, 'race');
        }

        if ($character->race_id) {
            $this->proficiencyService->populateFromRace($character);
            $this->featureService->populateFromRace($character);
        }
    }

    /**
     * Handle background change - clear old background proficiencies/features and populate new ones.
     */
    private function handleBackgroundChange($character, ?int $oldBackgroundId): void
    {
        if ($oldBackgroundId !== null) {
            $this->proficiencyService->clearProficiencies($character, 'background');
            $this->featureService->clearFeatures($character, 'background');
        }

        if ($character->background_id) {
            $this->proficiencyService->populateFromBackground($character);
            $this->featureService->populateFromBackground($character);
        }
    }

    /**
     * Handle level change - add any new class features for the new level.
     */
    private function handleLevelChange($character): void
    {
        // Just re-populate class features - the service handles duplicates
        if ($character->class_id) {
            $this->featureService->populateFromClass($character);
        }
    }
}
