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
    public function __construct(
        private readonly FeatureUseService $featureUseService
    ) {}

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
                    $feature->level,
                    $charClass->level // Pass class level for uses initialization
                );
            }
        }

        $character->load('features');
    }

    /**
     * Mechanical trait categories that represent actual game features.
     * Non-mechanical categories (description, flavor, characteristics, null) are filtered out.
     */
    private const MECHANICAL_TRAIT_CATEGORIES = ['species', 'subspecies', 'feature'];

    /**
     * Populate racial traits for the character.
     *
     * Only includes mechanical traits (species, subspecies, feature categories).
     * Filters out flavor text like "Description", "Age", "Alignment", "Names".
     */
    public function populateFromRace(Character $character): void
    {
        if (! $character->race_slug) {
            return;
        }

        $race = $character->race;
        if (! $race) {
            return;
        }

        // Get mechanical racial traits from entity_traits table
        // Filter to only include game-relevant features (not flavor text)
        // Note: whereIn() automatically excludes null categories (Age, Alignment, etc.)
        $traits = $race->traits()
            ->whereIn('category', self::MECHANICAL_TRAIT_CATEGORIES)
            ->get();

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
     *
     * Only includes actual background features (category='feature').
     * Filters out characteristics tables (personality traits, ideals, bonds, flaws).
     */
    public function populateFromBackground(Character $character): void
    {
        if (! $character->background_slug) {
            return;
        }

        $background = $character->background;
        if (! $background) {
            return;
        }

        // Get background features from entity_traits table
        // Only include 'feature' category (like "Military Rank", "Shelter of the Faithful")
        // Exclude 'characteristics' category (random personality tables)
        $traits = $background->traits()
            ->where('category', 'feature')
            ->get();

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
        $subclass = CharacterClass::where('slug', $subclassSlug)->first();

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
                $feature->level,
                $characterLevel // Pass class level for uses initialization
            );

            // Assign spells granted by this feature
            $this->assignSpellsFromFeature($character, $feature, $characterLevel, $classSlug);
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
     * Spells with NULL level_requirement are granted immediately (e.g., bonus cantrips).
     *
     * @param  string  $classSlug  The base class slug for multiclass spellcasting support (Issue #715)
     */
    private function assignSpellsFromFeature(Character $character, ClassFeature $feature, int $characterLevel, string $classSlug): void
    {
        // Only auto-assign spells for "always prepared" features (Cleric domains, Paladin oaths, etc.)
        // Warlock expanded spells (is_always_prepared=false) just expand the available pool
        // and are handled by SpellManagerService::getAvailableSpells()
        if (! $feature->is_always_prepared) {
            return;
        }

        // Load spells with pivot data (level_requirement, is_cantrip)
        // Include spells where level_requirement <= character level OR level_requirement is NULL
        $featureSpells = $feature->spells()
            ->where(function ($query) use ($characterLevel) {
                $query->where('entity_spells.level_requirement', '<=', $characterLevel)
                    ->orWhereNull('entity_spells.level_requirement');
            })
            ->get();

        if ($featureSpells->isEmpty()) {
            return;
        }

        foreach ($featureSpells as $spell) {
            $levelAcquired = $spell->pivot->level_requirement ?? $feature->level;

            $this->createSpellIfNotExists(
                $character,
                $spell->slug,
                'subclass',
                $levelAcquired,
                'always_prepared',
                $classSlug
            );
        }
    }

    /**
     * Create a character spell if it doesn't already exist.
     *
     * Uses firstOrCreate with only (character_id, spell_slug) as the key
     * to match the database unique constraint. This handles cases where
     * a character already has the spell from a different source (e.g.,
     * Divine Soul origin granting heroism before Peace Domain also grants it).
     *
     * If the spell already exists and the new grant is 'always_prepared',
     * upgrades the existing spell to always_prepared (domain spells should
     * always be prepared even if already known from another source).
     *
     * @param  string|null  $classSlug  The class that grants this spell (Issue #715)
     */
    private function createSpellIfNotExists(
        Character $character,
        string $spellSlug,
        string $source,
        int $levelAcquired,
        string $preparationStatus,
        ?string $classSlug = null
    ): void {
        $spell = CharacterSpell::firstOrCreate(
            [
                'character_id' => $character->id,
                'spell_slug' => $spellSlug,
            ],
            [
                'source' => $source,
                'level_acquired' => $levelAcquired,
                'preparation_status' => $preparationStatus,
                'class_slug' => $classSlug,
            ]
        );

        // Upgrade to always_prepared if the new grant is stronger
        if ($preparationStatus === 'always_prepared' && $spell->preparation_status !== 'always_prepared') {
            $spell->update(['preparation_status' => 'always_prepared']);
        }
    }

    /**
     * Grant newly unlocked subclass spells based on character's current level.
     *
     * Called during level-up to grant spells from existing subclass features
     * that have level_requirement matching the new level.
     *
     * @param  string  $classSlug  The base class slug to find the subclass
     */
    public function grantUnlockedSubclassSpells(Character $character, string $classSlug): void
    {
        // Get the character's class info
        $characterClass = $character->characterClasses()
            ->where('class_slug', $classSlug)
            ->first();

        if (! $characterClass || ! $characterClass->subclass_slug) {
            return;
        }

        $subclass = $characterClass->subclass;
        if (! $subclass) {
            return;
        }

        $characterLevel = $characterClass->level;

        // Get subclass features that grant spells
        // Note: is_always_prepared is an accessor, so we filter in PHP
        $features = $subclass->features()
            ->whereNull('parent_feature_id')
            ->get()
            ->filter(fn ($feature) => $feature->is_always_prepared);

        foreach ($features as $feature) {
            $this->assignSpellsFromFeature($character, $feature, $characterLevel, $classSlug);
        }

        $character->load('spells');
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
        $subclass = CharacterClass::where('slug', $subclassSlug)->first();

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
            ->flatMap(fn ($feature) => $feature->spells->pluck('slug'))
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
     * If classLevel is provided, initializes feature uses from class counters.
     */
    private function createFeatureIfNotExists(
        Character $character,
        string $featureType,
        int $featureId,
        string $source,
        int $levelAcquired,
        ?int $classLevel = null
    ): void {
        $exists = $character->features()
            ->where('feature_type', $featureType)
            ->where('feature_id', $featureId)
            ->where('source', $source)
            ->exists();

        if ($exists) {
            return;
        }

        $characterFeature = CharacterFeature::create([
            'character_id' => $character->id,
            'feature_type' => $featureType,
            'feature_id' => $featureId,
            'source' => $source,
            'level_acquired' => $levelAcquired,
        ]);

        // Initialize feature uses if this is a class feature with a counter
        if ($featureType === ClassFeature::class && $classLevel !== null) {
            $this->featureUseService->initializeUsesForFeature($characterFeature, $classLevel);
        }
    }
}
