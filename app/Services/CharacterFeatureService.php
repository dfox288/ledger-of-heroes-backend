<?php

namespace App\Services;

use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterFeature;
use App\Models\CharacterSpell;
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
     * Also assigns any spells granted by subclass features.
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

        $characterLevel = $characterClass->level;

        // Get subclass features up to character's level in this class
        // Note: Subclass features have is_optional=true because they're only available
        // IF you choose that subclass. But once chosen, they are automatically granted.
        $features = $subclass->features()
            ->where('level', '<=', $characterLevel)
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

            // Assign spells granted by this feature
            $this->assignSpellsFromFeature($character, $feature, $characterLevel);
        }

        $character->load(['features', 'spells']);
    }

    /**
     * Assign spells granted by a class feature to the character.
     *
     * Only assigns spells when the feature has is_always_prepared=true (e.g., Cleric
     * Domain Spells, Paladin Oath Spells). Features with is_always_prepared=false
     * (e.g., Warlock Expanded Spell List) only expand the available spell pool -
     * those spells are handled by SpellManagerService::getAvailableSpells() and
     * the player must still choose them.
     *
     * Respects level_requirement from the entity_spells pivot, so higher-level
     * domain spells aren't assigned until the character reaches that level.
     */
    private function assignSpellsFromFeature(Character $character, ClassFeature $feature, int $characterLevel): void
    {
        // Only auto-assign spells for "always prepared" features (Cleric domains, Paladin oaths, etc.)
        // Warlock expanded spells (is_always_prepared=false) just expand the available pool
        // and are handled by SpellManagerService::getAvailableSpells()
        if (! $feature->is_always_prepared) {
            return;
        }

        // Load spells with pivot data (level_requirement, is_cantrip)
        $featureSpells = $feature->spells()
            ->wherePivot('level_requirement', '<=', $characterLevel)
            ->get();

        if ($featureSpells->isEmpty()) {
            return;
        }

        foreach ($featureSpells as $spell) {
            $levelAcquired = $spell->pivot->level_requirement ?? $feature->level;

            $this->createSpellIfNotExists(
                $character,
                $spell->full_slug,
                'subclass',
                $levelAcquired,
                'always_prepared'
            );
        }
    }

    /**
     * Create a character spell if it doesn't already exist.
     */
    private function createSpellIfNotExists(
        Character $character,
        string $spellSlug,
        string $source,
        int $levelAcquired,
        string $preparationStatus
    ): void {
        $exists = $character->spells()
            ->where('spell_slug', $spellSlug)
            ->where('source', $source)
            ->exists();

        if ($exists) {
            return;
        }

        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $spellSlug,
            'source' => $source,
            'level_acquired' => $levelAcquired,
            'preparation_status' => $preparationStatus,
        ]);
    }

    /**
     * Remove subclass features and their spells from a character for a specific subclass.
     *
     * Called when a subclass choice is undone. Only removes features and spells belonging
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

        // Get all features for this subclass
        $subclassFeatures = $subclass->features()->with('spells')->get();

        if ($subclassFeatures->isEmpty()) {
            return;
        }

        $subclassFeatureIds = $subclassFeatures->pluck('id')->toArray();

        // Collect all spell slugs granted by this subclass's features
        $spellSlugs = $subclassFeatures
            ->flatMap(fn ($feature) => $feature->spells->pluck('full_slug'))
            ->unique()
            ->toArray();

        // Delete spells from this specific subclass
        if (! empty($spellSlugs)) {
            $character->spells()
                ->where('source', 'subclass')
                ->whereIn('spell_slug', $spellSlugs)
                ->delete();
        }

        // Delete only features from this specific subclass
        $character->features()
            ->where('source', 'subclass')
            ->where('feature_type', ClassFeature::class)
            ->whereIn('feature_id', $subclassFeatureIds)
            ->delete();

        $character->load(['features', 'spells']);
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
