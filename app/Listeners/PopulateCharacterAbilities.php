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
     * When race_id or background_id changes, we need to:
     * 1. Clear features/proficiencies from the old source
     * 2. Re-populate from the new source
     *
     * Note: Class changes are now handled via CharacterClassPivot events,
     * not through the Character model directly (multiclass support).
     */
    public function handle(CharacterUpdated $event): void
    {
        $character = $event->character;
        $original = $character->getOriginal();

        // Check if race changed
        if ($this->hasChanged($original, $character, 'race_id')) {
            $this->handleRaceChange($character, $original['race_id'] ?? null);
        }

        // Check if background changed
        if ($this->hasChanged($original, $character, 'background_id')) {
            $this->handleBackgroundChange($character, $original['background_id'] ?? null);
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
}
