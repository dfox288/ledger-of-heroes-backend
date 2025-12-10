<?php

namespace App\Services;

use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterFeature;
use App\Models\CharacterTrait;
use App\Models\ClassFeature;

class CharacterFeatureService
{
    /**
     * Populate class features for the character up to their current level.
     *
     * Iterates over all character classes (multiclass support) and populates
     * features from each class up to the character's level in that class.
     */
    public function populateFromClass(Character $character): void
    {
        $character->loadMissing('characterClasses.characterClass');

        if ($character->characterClasses->isEmpty()) {
            return;
        }

        foreach ($character->characterClasses as $charClass) {
            $class = $charClass->characterClass;
            if (! $class) {
                continue;
            }

            // Get non-optional class features up to character's level in this class
            $features = $class->features()
                ->where('is_optional', false)
                ->where('level', '<=', $charClass->level)
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
     * Populate subclass features for a character up to their current level in that class.
     *
     * Called when a subclass is selected via the choice system.
     *
     * @param  string  $classSlug  The base class slug (to find the character's level in that class)
     * @param  string  $subclassSlug  The selected subclass slug
     */
    public function populateFromSubclass(Character $character, string $classSlug, string $subclassSlug): void
    {
        // Get the character's level in this class
        $characterClass = $character->characterClasses()
            ->where('class_slug', $classSlug)
            ->first();

        if (! $characterClass) {
            return;
        }

        // Load the subclass
        $subclass = CharacterClass::where('full_slug', $subclassSlug)->first();

        if (! $subclass) {
            return;
        }

        // Get subclass features up to character's level in this class
        // Note: Subclass features have is_optional=true because they're only available
        // IF you choose that subclass. But once chosen, they are automatically granted.
        $features = $subclass->features()
            ->where('level', '<=', $characterClass->level)
            ->whereNull('parent_feature_id') // Exclude child features (choice options)
            ->get();

        foreach ($features as $feature) {
            $this->createFeatureIfNotExists(
                $character,
                ClassFeature::class,
                $feature->id,
                'subclass',
                $feature->level
            );
        }

        $character->load('features');
    }

    /**
     * Remove subclass features from a character for a specific subclass.
     *
     * Called when a subclass choice is undone. Only removes features belonging
     * to the specified subclass, preserving other subclass features (multiclass support).
     *
     * @param  string  $subclassSlug  The subclass whose features should be removed
     */
    public function clearSubclassFeatures(Character $character, string $subclassSlug): void
    {
        // Load the subclass to get its feature IDs
        $subclass = CharacterClass::where('full_slug', $subclassSlug)->first();

        if (! $subclass) {
            return;
        }

        // Get all feature IDs for this subclass
        $subclassFeatureIds = $subclass->features()->pluck('id')->toArray();

        if (empty($subclassFeatureIds)) {
            return;
        }

        // Delete only features from this specific subclass
        $character->features()
            ->where('source', 'subclass')
            ->where('feature_type', ClassFeature::class)
            ->whereIn('feature_id', $subclassFeatureIds)
            ->delete();

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
