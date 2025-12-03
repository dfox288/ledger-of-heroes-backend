<?php

namespace App\Services;

use App\Models\Character;
use App\Models\CharacterFeature;
use App\Models\CharacterTrait;
use App\Models\ClassFeature;

class CharacterFeatureService
{
    /**
     * Populate class features for the character up to their current level.
     */
    public function populateFromClass(Character $character): void
    {
        if (! $character->class_id) {
            return;
        }

        $characterClass = $character->characterClass;
        if (! $characterClass) {
            return;
        }

        // Get non-optional class features up to character's level
        $features = $characterClass->features()
            ->where('is_optional', false)
            ->where('level', '<=', $character->level)
            ->whereNull('parent_feature_id') // Exclude child features (choice options)
            ->get();

        foreach ($features as $feature) {
            $this->createFeatureIfNotExists(
                $character,
                ClassFeature::class,
                $feature->id,
                'class',
                $feature->level
            );
        }

        $character->load('features');
    }

    /**
     * Populate racial traits for the character.
     */
    public function populateFromRace(Character $character): void
    {
        if (! $character->race_id) {
            return;
        }

        $race = $character->race;
        if (! $race) {
            return;
        }

        // Get racial traits from entity_traits table
        $traits = $race->traits;

        foreach ($traits as $trait) {
            $this->createFeatureIfNotExists(
                $character,
                CharacterTrait::class,
                $trait->id,
                'race',
                1 // Racial traits are acquired at level 1
            );
        }

        $character->load('features');
    }

    /**
     * Populate background features/traits for the character.
     */
    public function populateFromBackground(Character $character): void
    {
        if (! $character->background_id) {
            return;
        }

        $background = $character->background;
        if (! $background) {
            return;
        }

        // Get background traits from entity_traits table
        $traits = $background->traits;

        foreach ($traits as $trait) {
            $this->createFeatureIfNotExists(
                $character,
                CharacterTrait::class,
                $trait->id,
                'background',
                1 // Background traits are acquired at level 1
            );
        }

        $character->load('features');
    }

    /**
     * Populate all features from class, race, and background.
     */
    public function populateAll(Character $character): void
    {
        $this->populateFromClass($character);
        $this->populateFromRace($character);
        $this->populateFromBackground($character);
    }

    /**
     * Get all features for a character with their related feature data.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getCharacterFeatures(Character $character)
    {
        return $character->features()
            ->with('feature')
            ->get();
    }

    /**
     * Clear all features for a character from a specific source.
     */
    public function clearFeatures(Character $character, string $source): void
    {
        $character->features()
            ->where('source', $source)
            ->delete();

        $character->load('features');
    }

    /**
     * Create a character feature if it doesn't already exist.
     */
    private function createFeatureIfNotExists(
        Character $character,
        string $featureType,
        int $featureId,
        string $source,
        int $levelAcquired
    ): void {
        $exists = $character->features()
            ->where('feature_type', $featureType)
            ->where('feature_id', $featureId)
            ->where('source', $source)
            ->exists();

        if ($exists) {
            return;
        }

        CharacterFeature::create([
            'character_id' => $character->id,
            'feature_type' => $featureType,
            'feature_id' => $featureId,
            'source' => $source,
            'level_acquired' => $levelAcquired,
        ]);
    }
}
