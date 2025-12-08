<?php

namespace App\Services;

use App\Exceptions\SpellManagementException;
use App\Models\Character;
use App\Models\CharacterSpell;
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
     * - Class spell list (from primary class)
     * - Min spell level (optional) - use 1 to exclude cantrips
     * - Max spell level (optional)
     * - Excludes already known spells (unless includeKnown is true)
     */
    public function getAvailableSpells(Character $character, ?int $minLevel = null, ?int $maxLevel = null, bool $includeKnown = false): Collection
    {
        $class = $character->primary_class;

        if (! $class) {
            return collect();
        }

        // Get the base class (for subclasses, we need the parent's spell list)
        $baseClass = $class->parent_class_id ? $class->parentClass : $class;

        $query = $baseClass->spells();

        // Exclude already known spells unless includeKnown is true
        if (! $includeKnown) {
            $knownSpellSlugs = $character->spells()->pluck('spell_slug');
            $query->whereNotIn('spells.full_slug', $knownSpellSlugs);
        }

        if ($minLevel !== null) {
            $query->where('level', '>=', $minLevel);
        }

        if ($maxLevel !== null) {
            $query->where('level', '<=', $maxLevel);
        }

        return $query->with('spellSchool')->get();
    }

    /**
     * Learn a new spell for the character.
     *
     * @throws SpellManagementException
     */
    public function learnSpell(Character $character, Spell $spell, string $source = 'class'): CharacterSpell
    {
        // Validate spell is on class list
        if (! $this->isSpellOnClassList($character, $spell)) {
            throw SpellManagementException::spellNotOnClassList($spell, $character);
        }

        // Validate spell level is accessible
        $maxSpellLevel = $this->getMaxSpellLevelForCharacter($character);
        if ($spell->level > $maxSpellLevel) {
            throw SpellManagementException::spellLevelTooHigh($spell, $character, $maxSpellLevel);
        }

        // Check if already known
        if ($this->characterKnowsSpell($character, $spell)) {
            throw SpellManagementException::spellAlreadyKnown($spell, $character);
        }

        return CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $spell->full_slug,
            'preparation_status' => 'known',
            'source' => $source,
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
            ->where('spell_slug', $spell->full_slug)
            ->first();

        if (! $characterSpell) {
            throw SpellManagementException::spellNotKnown($spell, $character);
        }

        $characterSpell->delete();
    }

    /**
     * Prepare a spell for casting.
     *
     * @throws SpellManagementException
     */
    public function prepareSpell(Character $character, Spell $spell): CharacterSpell
    {
        $characterSpell = $character->spells()
            ->where('spell_slug', $spell->full_slug)
            ->first();

        if (! $characterSpell) {
            throw SpellManagementException::spellNotKnown($spell, $character);
        }

        // Cantrips cannot be prepared (they're always ready)
        if ($spell->level === 0) {
            throw SpellManagementException::cannotPrepareCantrip($spell);
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
     * Unprepare a spell.
     *
     * @throws SpellManagementException
     */
    public function unprepareSpell(Character $character, Spell $spell): CharacterSpell
    {
        $characterSpell = $character->spells()
            ->where('spell_slug', $spell->full_slug)
            ->first();

        if (! $characterSpell) {
            throw SpellManagementException::spellNotKnown($spell, $character);
        }

        // Always-prepared spells cannot be unprepared
        if ($characterSpell->isAlwaysPrepared()) {
            throw SpellManagementException::cannotUnprepareAlwaysPrepared($spell);
        }

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
     * Check if a spell is on the character's class spell list.
     */
    private function isSpellOnClassList(Character $character, Spell $spell): bool
    {
        $class = $character->primary_class;

        if (! $class) {
            return false;
        }

        // Get the base class (for subclasses)
        $baseClass = $class->parent_class_id ? $class->parentClass : $class;

        return $baseClass->spells()->where('spells.id', $spell->id)->exists();
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
     * Check if character already knows this spell.
     */
    private function characterKnowsSpell(Character $character, Spell $spell): bool
    {
        return $character->spells()->where('spell_slug', $spell->full_slug)->exists();
    }

    /**
     * Get preparation limit for the character.
     */
    private function getPreparationLimit(Character $character): ?int
    {
        $class = $character->primary_class;

        if (! $class) {
            return null;
        }

        $baseClassName = $class->parent_class_id
            ? strtolower($class->parentClass->name ?? '')
            : strtolower($class->name);

        // Get the spellcasting ability
        $spellcastingAbility = $class->effective_spellcasting_ability;

        if (! $spellcastingAbility) {
            return null;
        }

        // Get the character's ability modifier for spellcasting
        $abilityScore = $character->getAbilityScore($spellcastingAbility->code);

        if ($abilityScore === null) {
            return null;
        }

        $abilityModifier = $this->statCalculator->abilityModifier($abilityScore);

        return $this->statCalculator->getPreparationLimit($baseClassName, $character->total_level, $abilityModifier);
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
}
