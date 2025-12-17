<?php

namespace App\Services;

use App\Exceptions\SpellManagementException;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterSpell;
use App\Models\ClassFeature;
use App\Models\Spell;
use Illuminate\Support\Collection;

class SpellManagerService
{
    public function __construct(
        private CharacterStatCalculator $statCalculator,
        private SpellSlotService $spellSlotService
    ) {}

    /**
     * Get all spells known by a character.
     */
    public function getCharacterSpells(Character $character): Collection
    {
        return $character->spells()->with('spell.spellSchool')->get();
    }

    /**
     * Get spells available for a character to learn.
     *
     * Filters by:
     * - Class spell list (from primary class, or override with $classSlug)
     * - Subclass expanded spells (if a subclass is selected and no override)
     * - Min spell level (optional) - use 1 to exclude cantrips
     * - Max spell level (optional)
     * - Excludes already known spells (unless includeKnown is true)
     *
     * @param  string|null  $classSlug  Optional class to get spells from instead of character's class
     *                                  (e.g., "phb:druid" for Nature Domain's druid cantrip choice)
     */
    public function getAvailableSpells(Character $character, ?int $minLevel = null, ?int $maxLevel = null, bool $includeKnown = false, ?string $classSlug = null): Collection
    {
        // If a specific class is requested (e.g., for subclass features granting spells from other lists)
        if ($classSlug) {
            return $this->getSpellsFromClass($character, $classSlug, $minLevel, $maxLevel, $includeKnown);
        }

        $classPivot = $character->characterClasses()->where('is_primary', true)->first();

        if (! $classPivot) {
            return collect();
        }

        $class = $classPivot->characterClass;

        if (! $class) {
            return collect();
        }

        // Get the base class (for subclasses, we need the parent's spell list)
        $baseClass = $class->parent_class_id ? $class->parentClass : $class;

        // Get known spell slugs to exclude (if needed)
        $knownSpellSlugs = $includeKnown
            ? collect()
            : $character->spells()->pluck('spell_slug');

        // Get base class spells
        $query = $baseClass->spells();

        if (! $includeKnown && $knownSpellSlugs->isNotEmpty()) {
            $query->whereNotIn('spells.slug', $knownSpellSlugs);
        }

        if ($minLevel !== null) {
            $query->where('level', '>=', $minLevel);
        }

        if ($maxLevel !== null) {
            $query->where('level', '<=', $maxLevel);
        }

        $baseSpells = $query->with('spellSchool')->get();

        // Get expanded spells from subclass features (if subclass selected)
        $expandedSpells = $this->getExpandedSpellsFromSubclass(
            $classPivot,
            $knownSpellSlugs,
            $minLevel,
            $maxLevel
        );

        // Merge, deduplicate, and sort alphabetically
        return $baseSpells->merge($expandedSpells)
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    /**
     * Get expanded spells from subclass features.
     *
     * These are spells granted via features like "Expanded Spell List (The Hexblade)"
     * that add to the character's available spell choices.
     */
    private function getExpandedSpellsFromSubclass(
        $classPivot,
        Collection $knownSpellSlugs,
        ?int $minLevel,
        ?int $maxLevel
    ): Collection {
        // No subclass selected
        if (! $classPivot->subclass_slug) {
            return collect();
        }

        $subclass = $classPivot->subclass;

        if (! $subclass) {
            return collect();
        }

        $characterLevel = $classPivot->level;

        // Get features from the subclass that might have spell lists
        // These are typically "Expanded Spell List", "Domain Spells", etc.
        $features = $subclass->features()
            ->where('level', '<=', $characterLevel)
            ->whereNull('parent_feature_id')
            ->get();

        if ($features->isEmpty()) {
            return collect();
        }

        $featureIds = $features->pluck('id')->toArray();

        // Query spells linked to these features via entity_spells
        $query = Spell::query()
            ->join('entity_spells', 'spells.id', '=', 'entity_spells.spell_id')
            ->where('entity_spells.reference_type', ClassFeature::class)
            ->whereIn('entity_spells.reference_id', $featureIds)
            ->where('entity_spells.level_requirement', '<=', $characterLevel)
            ->select('spells.*');

        // Exclude already known spells
        if ($knownSpellSlugs->isNotEmpty()) {
            $query->whereNotIn('spells.slug', $knownSpellSlugs);
        }

        if ($minLevel !== null) {
            $query->where('spells.level', '>=', $minLevel);
        }

        if ($maxLevel !== null) {
            $query->where('spells.level', '<=', $maxLevel);
        }

        return $query->with('spellSchool')->get();
    }

    /**
     * Get spells from a specific class's spell list.
     *
     * Used when a feature grants spell choices from a different class's list
     * (e.g., Nature Domain's "Acolyte of Nature" grants a druid cantrip).
     */
    private function getSpellsFromClass(
        Character $character,
        string $classSlug,
        ?int $minLevel,
        ?int $maxLevel,
        bool $includeKnown
    ): Collection {
        // Find the class by slug
        $class = CharacterClass::where('slug', $classSlug)
            ->whereNull('parent_class_id') // Only base classes have spell lists
            ->first();

        if (! $class) {
            return collect();
        }

        // Get known spell slugs to exclude (if needed)
        $knownSpellSlugs = $includeKnown
            ? collect()
            : $character->spells()->pluck('spell_slug');

        $query = $class->spells();

        if (! $includeKnown && $knownSpellSlugs->isNotEmpty()) {
            $query->whereNotIn('spells.slug', $knownSpellSlugs);
        }

        if ($minLevel !== null) {
            $query->where('level', '>=', $minLevel);
        }

        if ($maxLevel !== null) {
            $query->where('level', '<=', $maxLevel);
        }

        return $query->with('spellSchool')
            ->orderBy('name')
            ->get();
    }

    /**
     * Learn a new spell for the character.
     *
     * @param  string|null  $classSlug  Issue #692: Class that grants the spell (for multiclass support)
     *
     * @throws SpellManagementException
     */
    public function learnSpell(Character $character, Spell $spell, string $source = 'class', ?string $classSlug = null): CharacterSpell
    {
        // Validate spell is on class list (use specified class for multiclass)
        if (! $this->isSpellOnClassList($character, $spell, $classSlug)) {
            throw SpellManagementException::spellNotOnClassList($spell, $character);
        }

        // Validate spell level is accessible (use class-specific max level for multiclass)
        $maxSpellLevel = $this->getMaxSpellLevelForClass($character, $classSlug);
        if ($spell->level > $maxSpellLevel) {
            throw SpellManagementException::spellLevelTooHigh($spell, $character, $maxSpellLevel);
        }

        // Check if already known
        if ($this->characterKnowsSpell($character, $spell)) {
            throw SpellManagementException::spellAlreadyKnown($spell, $character);
        }

        // Issue #692: If no class_slug provided and source is class, default to primary class
        if ($classSlug === null && $source === 'class') {
            $classSlug = $character->primary_class?->slug;
        }

        return CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $spell->slug,
            'preparation_status' => 'known',
            'source' => $source,
            'class_slug' => $classSlug,
            'level_acquired' => $character->total_level,
        ]);
    }

    /**
     * Remove a spell from a character's known spells.
     *
     * @throws SpellManagementException
     */
    public function forgetSpell(Character $character, Spell $spell): void
    {
        $characterSpell = $character->spells()
            ->where('spell_slug', $spell->slug)
            ->first();

        if (! $characterSpell) {
            throw SpellManagementException::spellNotKnown($spell, $character);
        }

        $characterSpell->delete();
    }

    /**
     * Prepare a spell for casting.
     *
     * For prepared casters (Cleric, Druid, Paladin, Artificer), this will auto-add
     * spells from their class list with source='prepared_from_list'. These spells
     * are deleted when unprepared (ephemeral divine access).
     *
     * @param  string|null  $classSlug  Class to use for multiclass (if null, uses primary class)
     *
     * @throws SpellManagementException
     */
    public function prepareSpell(Character $character, Spell $spell, ?string $classSlug = null): CharacterSpell
    {
        // Cantrips cannot be prepared (check first to avoid creating orphaned rows)
        if ($spell->level === 0) {
            throw SpellManagementException::cannotPrepareCantrip($spell);
        }

        $characterSpell = $character->spells()
            ->where('spell_slug', $spell->slug)
            ->first();

        // If spell not in character_spells, try auto-add for prepared casters
        if (! $characterSpell) {
            $characterSpell = $this->tryAutoAddForPreparedCaster($character, $spell, $classSlug);
        }

        if (! $characterSpell) {
            throw SpellManagementException::spellNotKnown($spell, $character);
        }

        // Already prepared - idempotent success
        if ($characterSpell->preparation_status === 'prepared') {
            return $characterSpell;
        }

        // Check preparation limit
        $preparationLimit = $this->getPreparationLimit($character);
        $currentPrepared = $this->countPreparedSpells($character);

        if ($preparationLimit !== null && $currentPrepared >= $preparationLimit) {
            throw SpellManagementException::preparationLimitReached($character, $preparationLimit);
        }

        $characterSpell->update(['preparation_status' => 'prepared']);

        return $characterSpell->fresh();
    }

    /**
     * Try to auto-add a spell for a prepared caster (Cleric, Druid, Paladin, Artificer).
     *
     * Prepared casters can prepare any spell from their class list without first
     * "learning" it. This creates an ephemeral character_spell with source='prepared_from_list'
     * that is deleted when unprepared.
     *
     * @param  string|null  $classSlug  Class to use for multiclass (if null, uses primary class)
     * @return CharacterSpell|null The created spell, or null if conditions not met
     *
     * @throws SpellManagementException If validation fails
     */
    private function tryAutoAddForPreparedCaster(Character $character, Spell $spell, ?string $classSlug = null): ?CharacterSpell
    {
        // Only for prepared casters (check the specified class for multiclass)
        if (! $this->isPreparedCaster($character, $classSlug)) {
            return null;
        }

        // Validate spell is on class list
        if (! $this->isSpellOnClassList($character, $spell, $classSlug)) {
            throw SpellManagementException::spellNotOnClassList($spell, $character);
        }

        // Validate spell level is accessible (use class-specific max level)
        $maxSpellLevel = $this->getMaxSpellLevelForClass($character, $classSlug);
        if ($spell->level > $maxSpellLevel) {
            throw SpellManagementException::spellLevelTooHigh($spell, $character, $maxSpellLevel);
        }

        // Check preparation limit before creating
        $preparationLimit = $this->getPreparationLimit($character);
        $currentPrepared = $this->countPreparedSpells($character);

        if ($preparationLimit !== null && $currentPrepared >= $preparationLimit) {
            throw SpellManagementException::preparationLimitReached($character, $preparationLimit);
        }

        // Get class slug for the spell (use specified or fall back to primary class)
        $effectiveClassSlug = $classSlug ?? $character->primary_class?->slug;

        return CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $spell->slug,
            'preparation_status' => 'prepared',
            'source' => 'prepared_from_list',
            'class_slug' => $effectiveClassSlug,
            'level_acquired' => $character->total_level,
        ]);
    }

    /**
     * Check if a character's class is a prepared caster.
     *
     * Prepared casters (Cleric, Druid, Paladin, Artificer) have access to their
     * entire class spell list and prepare a subset daily.
     *
     * @param  string|null  $classSlug  If provided, check this specific class. If null, check primary class.
     */
    private function isPreparedCaster(Character $character, ?string $classSlug = null): bool
    {
        if ($classSlug !== null) {
            $classPivot = $this->findClassPivotBySlug($character, $classSlug);

            if (! $classPivot) {
                return false;
            }

            $class = $classPivot->characterClass;
        } else {
            $class = $character->primary_class;
        }

        if (! $class) {
            return false;
        }

        // Get the base class for subclasses
        $effectiveClass = $class->parent_class_id ? $class->parentClass : $class;

        return $effectiveClass?->spell_preparation_method === 'prepared';
    }

    /**
     * Unprepare a spell.
     *
     * For spells with source='prepared_from_list' (divine casters preparing from
     * class list), the row is deleted entirely. For other sources (spellbook,
     * known spells, etc.), the status is set to 'known'.
     *
     * @return CharacterSpell|null The updated spell, or null if deleted
     *
     * @throws SpellManagementException
     */
    public function unprepareSpell(Character $character, Spell $spell): ?CharacterSpell
    {
        $characterSpell = $character->spells()
            ->where('spell_slug', $spell->slug)
            ->first();

        if (! $characterSpell) {
            throw SpellManagementException::spellNotKnown($spell, $character);
        }

        // Cantrips cannot be unprepared (they're always ready)
        if ($spell->level === 0) {
            throw SpellManagementException::cannotUnprepareCantrip($spell);
        }

        // Always-prepared spells cannot be unprepared
        if ($characterSpell->isAlwaysPrepared()) {
            throw SpellManagementException::cannotUnprepareAlwaysPrepared($spell);
        }

        // Ephemeral divine preparations are deleted entirely
        if ($characterSpell->source === 'prepared_from_list') {
            $characterSpell->delete();

            return null;
        }

        // Permanent spell acquisitions (spellbook, known, etc.) are kept as 'known'
        $characterSpell->update(['preparation_status' => 'known']);

        return $characterSpell->fresh();
    }

    /**
     * Get spell slot information for the character.
     *
     * Returns consolidated slot data with tracked usage:
     * - slots: keyed by level with total/spent/available
     * - pact_magic: warlock slots with level/total/spent/available
     * - preparation_limit: max prepared spells for prepared casters
     * - prepared_count: current number of prepared spells
     */
    public function getSpellSlots(Character $character): array
    {
        $class = $character->primary_class;

        if (! $class) {
            return [
                'slots' => [],
                'pact_magic' => null,
                'preparation_limit' => null,
                'prepared_count' => 0,
            ];
        }

        $baseClassName = $class->parent_class_id
            ? strtolower($class->parentClass->name ?? '')
            : strtolower($class->name);

        // Get calculated slots from stat calculator
        $calculatedSlots = $this->statCalculator->getSpellSlots($baseClassName, $character->total_level);

        // Get tracked slot usage from database
        $trackedSlots = $this->spellSlotService->getSlots($character);

        // Merge calculated slots with tracked usage
        $consolidatedSlots = $this->mergeSlotData($calculatedSlots, $trackedSlots, $baseClassName);

        return [
            'slots' => $consolidatedSlots['slots'],
            'pact_magic' => $consolidatedSlots['pact_magic'],
            'preparation_limit' => $this->getPreparationLimit($character),
            'prepared_count' => $this->countPreparedSpells($character),
            'preparation_limits' => $this->getPerClassPreparationLimits($character),
        ];
    }

    /**
     * Merge calculated slot maximums with tracked usage data.
     */
    private function mergeSlotData(array $calculatedSlots, array $trackedSlots, string $baseClassName): array
    {
        $isWarlock = strtolower($baseClassName) === 'warlock';

        if ($isWarlock) {
            // Warlock uses pact magic
            $pactMagicSlot = null;

            if (! empty($calculatedSlots)) {
                // Warlock slots are keyed by level (e.g., [2 => 2] means 2 slots of level 2)
                $slotLevel = (int) array_key_first($calculatedSlots);
                $maxSlots = $calculatedSlots[$slotLevel];

                // Get tracked usage for this level
                $usedSlots = $trackedSlots['pact_magic'][$slotLevel]['used'] ?? 0;

                $pactMagicSlot = [
                    'level' => $slotLevel,
                    'total' => $maxSlots,
                    'spent' => $usedSlots,
                    'available' => max(0, $maxSlots - $usedSlots),
                ];
            }

            return [
                'slots' => [],
                'pact_magic' => $pactMagicSlot,
            ];
        }

        // Standard spellcasters
        $consolidatedSlots = [];

        foreach ($calculatedSlots as $level => $maxSlots) {
            $usedSlots = $trackedSlots['standard'][$level]['used'] ?? 0;

            // Use string keys to preserve level numbers in JSON
            $consolidatedSlots[(string) $level] = [
                'total' => $maxSlots,
                'spent' => $usedSlots,
                'available' => max(0, $maxSlots - $usedSlots),
            ];
        }

        return [
            'slots' => $consolidatedSlots,
            'pact_magic' => null,
        ];
    }

    /**
     * Check if a spell is on a character's class spell list or expanded spell list.
     *
     * @param  string|null  $classSlug  If provided, check this specific class. If null, check primary class.
     *                                  For multiclass characters, pass the class_slug to validate against
     *                                  that specific class's spell list.
     */
    private function isSpellOnClassList(Character $character, Spell $spell, ?string $classSlug = null): bool
    {
        // If a specific class is provided, find that class pivot
        if ($classSlug !== null) {
            $classPivot = $this->findClassPivotBySlug($character, $classSlug);
        } else {
            $classPivot = $character->characterClasses()->where('is_primary', true)->first();
        }

        if (! $classPivot) {
            return false;
        }

        $class = $classPivot->characterClass;

        if (! $class) {
            return false;
        }

        // Get the base class (for subclasses)
        $baseClass = $class->parent_class_id ? $class->parentClass : $class;

        // Check base class spell list
        if ($baseClass->spells()->where('spells.id', $spell->id)->exists()) {
            return true;
        }

        // Check subclass expanded spell list
        if ($classPivot->subclass_slug) {
            $subclass = $classPivot->subclass;

            if ($subclass) {
                $characterLevel = $classPivot->level;

                $featureIds = $subclass->features()
                    ->where('level', '<=', $characterLevel)
                    ->whereNull('parent_feature_id')
                    ->pluck('id')
                    ->toArray();

                if (! empty($featureIds)) {
                    $exists = Spell::query()
                        ->join('entity_spells', 'spells.id', '=', 'entity_spells.spell_id')
                        ->where('entity_spells.reference_type', ClassFeature::class)
                        ->whereIn('entity_spells.reference_id', $featureIds)
                        ->where('entity_spells.level_requirement', '<=', $characterLevel)
                        ->where('spells.id', $spell->id)
                        ->exists();

                    if ($exists) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Find a character's class pivot by class slug.
     *
     * Handles both base class slugs and subclass slugs - checks if the character
     * has levels in the specified class (or its parent base class).
     */
    private function findClassPivotBySlug(Character $character, string $classSlug): ?object
    {
        // First try direct class_slug match
        $pivot = $character->characterClasses()
            ->where('class_slug', $classSlug)
            ->first();

        if ($pivot) {
            return $pivot;
        }

        // Check if classSlug is a base class and character has a subclass of it
        $baseClass = CharacterClass::where('slug', $classSlug)
            ->whereNull('parent_class_id')
            ->first();

        if ($baseClass) {
            // Find if character has any subclass of this base class
            $pivot = $character->characterClasses()
                ->whereHas('characterClass', function ($query) use ($baseClass) {
                    $query->where('parent_class_id', $baseClass->id);
                })
                ->first();

            if ($pivot) {
                return $pivot;
            }
        }

        return null;
    }

    /**
     * Get the maximum spell level this character can cast.
     *
     * D&D 5e: Full casters learn up to (level + 1) / 2 rounded up
     * Level 1-2: 1st, Level 3-4: 2nd, Level 5-6: 3rd, etc.
     */
    private function getMaxSpellLevelForCharacter(Character $character): int
    {
        // For full casters: max spell level = ceil(level / 2) capped at 9
        // Level 1: 1st, Level 3: 2nd, Level 5: 3rd, etc.
        return min(9, (int) ceil($character->total_level / 2));
    }

    /**
     * Get the maximum spell level for a specific class (for multiclass support).
     *
     * D&D 5e Multiclass Rule: Each class prepares spells as if single-classed.
     * A Wizard 7 / Cleric 3 can only prepare Cleric spells up to level 2 (from Cleric 3),
     * even though they have 4th-level spell slots from combined caster levels.
     *
     * @param  string|null  $classSlug  If provided, use that class's level. If null, use total level.
     */
    private function getMaxSpellLevelForClass(Character $character, ?string $classSlug = null): int
    {
        if ($classSlug === null) {
            // No specific class - use total level (existing behavior)
            return $this->getMaxSpellLevelForCharacter($character);
        }

        $classPivot = $this->findClassPivotBySlug($character, $classSlug);

        if (! $classPivot) {
            // Character doesn't have this class - return 0 (can't cast any spells)
            return 0;
        }

        $classLevel = $classPivot->level;

        // For full casters: max spell level = ceil(level / 2) capped at 9
        // Level 1-2: 1st, Level 3-4: 2nd, Level 5-6: 3rd, etc.
        return min(9, (int) ceil($classLevel / 2));
    }

    /**
     * Check if character already knows this spell.
     */
    private function characterKnowsSpell(Character $character, Spell $spell): bool
    {
        return $character->spells()->where('spell_slug', $spell->slug)->exists();
    }

    /**
     * Get total preparation limit for the character.
     *
     * For multiclass characters, this is the SUM of all per-class preparation limits.
     * Per D&D 5e rules, each class prepares spells separately based on class level + ability modifier.
     */
    private function getPreparationLimit(Character $character): ?int
    {
        $perClassLimits = $this->getPerClassPreparationLimits($character);

        if (empty($perClassLimits)) {
            return null;
        }

        // Sum all per-class limits
        $total = 0;
        foreach ($perClassLimits as $classData) {
            $total += $classData['limit'];
        }

        return $total;
    }

    /**
     * Count currently prepared spells (excluding cantrips and always-prepared).
     */
    private function countPreparedSpells(Character $character): int
    {
        return $character->spells()
            ->where('preparation_status', 'prepared')
            ->whereHas('spell', fn ($q) => $q->where('level', '>', 0))
            ->count();
    }

    /**
     * Get per-class preparation limits for multiclass support (Issue #715).
     *
     * Returns preparation limits keyed by class slug:
     * [
     *     'phb:wizard' => ['limit' => 8, 'prepared' => 3],
     *     'phb:cleric' => ['limit' => 7, 'prepared' => 4],
     * ]
     *
     * Only includes classes that prepare spells (excludes known casters like Sorcerer).
     */
    private function getPerClassPreparationLimits(Character $character): array
    {
        $limits = [];

        $character->loadMissing('characterClasses.characterClass');

        foreach ($character->characterClasses as $pivot) {
            $class = $pivot->characterClass;

            if (! $class) {
                continue;
            }

            // Get the effective class (handle subclasses)
            $effectiveClass = $class->parent_class_id ? $class->parentClass : $class;

            if (! $effectiveClass) {
                continue;
            }

            // Skip non-casters and known casters (they don't prepare)
            $prepMethod = $effectiveClass->spell_preparation_method;

            if ($prepMethod === null || $prepMethod === 'known') {
                continue;
            }

            // Get spellcasting ability
            $spellcastingAbility = $effectiveClass->effective_spellcasting_ability;

            if (! $spellcastingAbility) {
                continue;
            }

            // Get ability modifier
            $abilityScore = $character->getAbilityScore($spellcastingAbility->code);

            if ($abilityScore === null) {
                continue;
            }

            $abilityModifier = $this->statCalculator->abilityModifier($abilityScore);

            // Calculate limit using class level (not total level)
            $limit = $this->statCalculator->getPreparationLimitFromClass(
                $effectiveClass,
                $pivot->level,
                $abilityModifier
            );

            if ($limit === null) {
                continue;
            }

            // Count prepared spells for this specific class
            $preparedCount = $character->spells()
                ->where('class_slug', $effectiveClass->slug)
                ->where('preparation_status', 'prepared')
                ->whereHas('spell', fn ($q) => $q->where('level', '>', 0))
                ->count();

            $limits[$effectiveClass->slug] = [
                'limit' => $limit,
                'prepared' => $preparedCount,
            ];
        }

        return $limits;
    }
}
