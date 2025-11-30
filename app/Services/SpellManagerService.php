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
        private CharacterStatCalculator $statCalculator
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
     * - Class spell list
     * - Max spell level (optional)
     * - Excludes already known spells
     */
    public function getAvailableSpells(Character $character, ?int $maxLevel = null): Collection
    {
        $class = $character->characterClass;

        if (! $class) {
            return collect();
        }

        // Get the base class (for subclasses, we need the parent's spell list)
        $baseClass = $class->parent_class_id ? $class->parentClass : $class;

        // Get spell IDs already known by the character
        $knownSpellIds = $character->spells()->pluck('spell_id');

        $query = $baseClass->spells()
            ->whereNotIn('spells.id', $knownSpellIds);

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
            'spell_id' => $spell->id,
            'preparation_status' => 'known',
            'source' => $source,
            'level_acquired' => $character->level,
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
            ->where('spell_id', $spell->id)
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
            ->where('spell_id', $spell->id)
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
            ->where('spell_id', $spell->id)
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
     */
    public function getSpellSlots(Character $character): array
    {
        $class = $character->characterClass;

        if (! $class) {
            return [
                'slots' => [],
                'preparation_limit' => null,
            ];
        }

        $baseClassName = $class->parent_class_id
            ? strtolower($class->parentClass->name ?? '')
            : strtolower($class->name);

        return [
            'slots' => $this->statCalculator->getSpellSlots($baseClassName, $character->level),
            'preparation_limit' => $this->getPreparationLimit($character),
        ];
    }

    /**
     * Check if a spell is on the character's class spell list.
     */
    private function isSpellOnClassList(Character $character, Spell $spell): bool
    {
        $class = $character->characterClass;

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
        return min(9, (int) ceil($character->level / 2));
    }

    /**
     * Check if character already knows this spell.
     */
    private function characterKnowsSpell(Character $character, Spell $spell): bool
    {
        return $character->spells()->where('spell_id', $spell->id)->exists();
    }

    /**
     * Get preparation limit for the character.
     */
    private function getPreparationLimit(Character $character): ?int
    {
        $class = $character->characterClass;

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

        return $this->statCalculator->getPreparationLimit($baseClassName, $character->level, $abilityModifier);
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
