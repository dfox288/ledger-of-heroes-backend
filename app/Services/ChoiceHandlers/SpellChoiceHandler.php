<?php

namespace App\Services\ChoiceHandlers;

use App\DTOs\PendingChoice;
use App\Exceptions\InvalidSelectionException;
use App\Models\Character;
use App\Models\CharacterClassPivot;
use App\Models\CharacterSpell;
use App\Models\Spell;
use Illuminate\Support\Collection;

class SpellChoiceHandler extends AbstractChoiceHandler
{
    public function getType(): string
    {
        return 'spell';
    }

    public function getChoices(Character $character): Collection
    {
        $choices = collect();

        foreach ($character->characterClasses as $pivot) {
            $class = $pivot->characterClass;

            // Skip non-casters
            if (! $this->isSpellcaster($class)) {
                continue;
            }

            // Get progression for this class at this level
            $progression = $class->levelProgression()
                ->where('level', $pivot->level)
                ->first();

            if (! $progression) {
                continue;
            }

            // Cantrip choices
            if ($progression->cantrips_known > 0) {
                $cantripChoice = $this->getCantripsChoice($character, $pivot, $progression);
                if ($cantripChoice) {
                    $choices->push($cantripChoice);
                }
            }

            // Spell known choices (only for known casters, not prepared casters)
            if ($progression->spells_known > 0) {
                $spellChoice = $this->getSpellsKnownChoice($character, $pivot, $progression);
                if ($spellChoice) {
                    $choices->push($spellChoice);
                }
            }
        }

        return $choices;
    }

    public function resolve(Character $character, PendingChoice $choice, array $selection): void
    {
        $selected = $selection['selected'] ?? [];
        if (empty($selected)) {
            throw new InvalidSelectionException($choice->id, 'empty', 'Selection cannot be empty');
        }

        // Validate spell IDs exist
        $spells = Spell::whereIn('id', $selected)->get();
        if ($spells->count() !== count($selected)) {
            throw new InvalidSelectionException(
                $choice->id,
                'invalid_spell_ids',
                'One or more spell IDs do not exist'
            );
        }

        $parsed = $this->parseChoiceId($choice->id);

        // Clear existing spells for this choice before adding new ones
        // This ensures re-submitting replaces rather than duplicates
        // Use the group (cantrips vs spells_known) to determine spell level filter
        $isCantrip = $parsed['group'] === 'cantrips';

        $character->spells()
            ->where('source', $parsed['source'])
            ->where('level_acquired', $parsed['level'])
            ->whereHas('spell', function ($query) use ($isCantrip) {
                if ($isCantrip) {
                    $query->where('level', 0);
                } else {
                    $query->where('level', '>', 0);
                }
            })
            ->delete();

        // Create CharacterSpell records
        foreach ($selected as $spellId) {
            CharacterSpell::create([
                'character_id' => $character->id,
                'spell_id' => $spellId,
                'source' => $parsed['source'],
                'level_acquired' => $parsed['level'],
                'preparation_status' => 'known',
            ]);
        }

        // Reload spells relationship
        $character->load('spells.spell');
    }

    public function canUndo(Character $character, PendingChoice $choice): bool
    {
        return true; // Spells can be changed during character creation
    }

    public function undo(Character $character, PendingChoice $choice): void
    {
        $parsed = $this->parseChoiceId($choice->id);

        // Delete CharacterSpell records for this specific choice
        // Use the group (cantrips vs spells_known) to determine spell level filter
        $isCantrip = $parsed['group'] === 'cantrips';

        $character->spells()
            ->where('source', $parsed['source'])
            ->where('level_acquired', $parsed['level'])
            ->whereHas('spell', function ($query) use ($isCantrip) {
                if ($isCantrip) {
                    $query->where('level', 0);
                } else {
                    $query->where('level', '>', 0);
                }
            })
            ->delete();

        $character->load('spells.spell');
    }

    /**
     * Check if class is a spellcaster
     */
    private function isSpellcaster($class): bool
    {
        return $class->spellcasting_ability_id !== null;
    }

    /**
     * Get cantrip choice for a class at a level
     */
    private function getCantripsChoice(
        Character $character,
        CharacterClassPivot $pivot,
        $progression
    ): ?PendingChoice {
        $class = $pivot->characterClass;

        // Count already known cantrips for this class
        $knownCantrips = $character->spells()
            ->where('source', 'class')
            ->where('level_acquired', $pivot->level)
            ->whereHas('spell', fn ($q) => $q->where('level', 0))
            ->get();

        $quantity = $progression->cantrips_known;
        $selected = $knownCantrips->pluck('spell_id')->map(fn ($id) => (string) $id)->toArray();
        $remaining = $quantity - count($selected);

        return new PendingChoice(
            id: $this->generateChoiceId('spell', 'class', $class->id, $pivot->level, 'cantrips'),
            type: 'spell',
            subtype: 'cantrip',
            source: 'class',
            sourceName: $class->name,
            levelGranted: $pivot->level,
            required: true,
            quantity: $quantity,
            remaining: $remaining,
            selected: $selected,
            options: null,
            optionsEndpoint: "/api/v1/characters/{$character->id}/available-spells?max_level=0",
            metadata: [
                'spell_level' => 0,
                'class_slug' => $class->slug,
            ],
        );
    }

    /**
     * Get spells known choice for a known caster at a level
     */
    private function getSpellsKnownChoice(
        Character $character,
        CharacterClassPivot $pivot,
        $progression
    ): ?PendingChoice {
        $class = $pivot->characterClass;

        // Count already known spells for this class (excluding cantrips)
        $knownSpells = $character->spells()
            ->where('source', 'class')
            ->where('level_acquired', $pivot->level)
            ->whereHas('spell', fn ($q) => $q->where('level', '>', 0))
            ->get();

        $quantity = $progression->spells_known;
        $selected = $knownSpells->pluck('spell_id')->map(fn ($id) => (string) $id)->toArray();
        $remaining = $quantity - count($selected);

        // Determine max spell level for this class level
        $maxSpellLevel = $this->getMaxSpellLevel($class, $pivot->level);

        return new PendingChoice(
            id: $this->generateChoiceId('spell', 'class', $class->id, $pivot->level, 'spells_known'),
            type: 'spell',
            subtype: 'spells_known',
            source: 'class',
            sourceName: $class->name,
            levelGranted: $pivot->level,
            required: true,
            quantity: $quantity,
            remaining: $remaining,
            selected: $selected,
            options: null,
            optionsEndpoint: "/api/v1/characters/{$character->id}/available-spells?max_level={$maxSpellLevel}",
            metadata: [
                'spell_level' => $maxSpellLevel,
                'class_slug' => $class->slug,
            ],
        );
    }

    /**
     * Determine the maximum spell level accessible at this character level
     */
    private function getMaxSpellLevel($class, int $characterLevel): int
    {
        // For known casters at level 1, they can only learn 1st level spells
        // This is a simplified version - could be enhanced with actual spell slot checking
        $progression = $class->levelProgression()
            ->where('level', $characterLevel)
            ->first();

        if (! $progression) {
            return 1;
        }

        // Check spell slot columns to determine max spell level
        $ordinals = ['1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th'];
        $maxLevel = 0;

        for ($i = 1; $i <= 9; $i++) {
            $column = "spell_slots_{$ordinals[$i - 1]}";
            if ($progression->{$column} > 0) {
                $maxLevel = $i;
            }
        }

        return max(1, $maxLevel);
    }
}
