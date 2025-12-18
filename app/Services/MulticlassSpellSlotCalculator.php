<?php

namespace App\Services;

use App\DTOs\PactSlotInfo;
use App\DTOs\SpellSlotResult;
use App\Models\Character;
use App\Models\MulticlassSpellSlot;

class MulticlassSpellSlotCalculator
{
    /**
     * Caster multipliers for calculating combined caster level.
     * PHB p164: Multiclassing Spellcasting rules.
     */
    private const CASTER_MULTIPLIERS = [
        'full' => 1.0,     // Wizard, Cleric, Druid, Bard, Sorcerer
        'half' => 0.5,     // Paladin, Ranger
        'third' => 0.334,  // Eldritch Knight, Arcane Trickster (round down)
        'pact' => 0.0,     // Warlock (separate pact magic, not combined)
        'none' => 0.0,     // Non-spellcasters
        'other' => 0.0,    // Unknown caster types
    ];

    /**
     * Warlock pact magic slots by level.
     * PHB Warlock class table.
     */
    private const PACT_SLOTS = [
        1 => ['count' => 1, 'level' => 1],
        2 => ['count' => 2, 'level' => 1],
        3 => ['count' => 2, 'level' => 2],
        4 => ['count' => 2, 'level' => 2],
        5 => ['count' => 2, 'level' => 3],
        6 => ['count' => 2, 'level' => 3],
        7 => ['count' => 2, 'level' => 4],
        8 => ['count' => 2, 'level' => 4],
        9 => ['count' => 2, 'level' => 5],
        10 => ['count' => 2, 'level' => 5],
        11 => ['count' => 3, 'level' => 5],
        12 => ['count' => 3, 'level' => 5],
        13 => ['count' => 3, 'level' => 5],
        14 => ['count' => 3, 'level' => 5],
        15 => ['count' => 3, 'level' => 5],
        16 => ['count' => 3, 'level' => 5],
        17 => ['count' => 4, 'level' => 5],
        18 => ['count' => 4, 'level' => 5],
        19 => ['count' => 4, 'level' => 5],
        20 => ['count' => 4, 'level' => 5],
    ];

    /**
     * Calculate spell slots for a character.
     */
    public function calculate(Character $character): SpellSlotResult
    {
        $character->load([
            'characterClasses.characterClass.levelProgression',
            'characterClasses.subclass.levelProgression',
        ]);

        $casterLevel = $this->calculateCasterLevel($character);
        $pactSlots = $this->getPactMagicSlots($character);

        // If no caster level and no pact slots, return empty result
        if ($casterLevel === 0 && $pactSlots === null) {
            return SpellSlotResult::empty();
        }

        // Get standard slots from multiclass table
        $standardSlots = null;
        if ($casterLevel > 0) {
            $slotData = MulticlassSpellSlot::forCasterLevel($casterLevel);
            if ($slotData) {
                $standardSlots = $slotData->toSlotsArray();
            }
        }

        return new SpellSlotResult(
            standardSlots: $standardSlots,
            pactSlots: $pactSlots,
        );
    }

    /**
     * Calculate combined caster level from all classes.
     *
     * D&D 5e PHB p164: Some subclasses (Eldritch Knight, Arcane Trickster)
     * grant spellcasting to otherwise non-caster base classes. We check
     * both the base class and subclass, using whichever has spellcasting.
     */
    public function calculateCasterLevel(Character $character): int
    {
        $character->load([
            'characterClasses.characterClass.levelProgression',
            'characterClasses.subclass.levelProgression',
        ]);

        $totalCasterLevel = 0;

        foreach ($character->characterClasses as $charClass) {
            $classLevel = $charClass->level;

            // Determine effective spellcasting type:
            // Check subclass first (Eldritch Knight, Arcane Trickster)
            // Fall back to base class
            $casterType = $this->getEffectiveCasterType($charClass);

            $multiplier = self::CASTER_MULTIPLIERS[$casterType] ?? 0.0;
            $totalCasterLevel += (int) floor($classLevel * $multiplier);
        }

        return $totalCasterLevel;
    }

    /**
     * Get the effective caster type for a character's class.
     *
     * Checks subclass first (for cases like Fighter/Eldritch Knight),
     * then falls back to base class.
     */
    private function getEffectiveCasterType($charClass): string
    {
        // Check subclass first - this handles Eldritch Knight and Arcane Trickster
        $subclass = $charClass->subclass;
        if ($subclass !== null) {
            $subclassType = $subclass->spellcasting_type ?? 'none';
            if ($subclassType !== 'none' && $subclassType !== 'unknown') {
                return $subclassType;
            }
        }

        // Fall back to base class
        $class = $charClass->characterClass;
        if ($class !== null) {
            return $class->spellcasting_type ?? 'none';
        }

        return 'none';
    }

    /**
     * Get Pact Magic slots if character has Warlock levels.
     */
    public function getPactMagicSlots(Character $character): ?PactSlotInfo
    {
        $character->load('characterClasses.characterClass');

        foreach ($character->characterClasses as $charClass) {
            $class = $charClass->characterClass;
            $casterType = $class->spellcasting_type ?? 'none';

            if ($casterType === 'pact') {
                $warlockLevel = min($charClass->level, 20);
                $pactData = self::PACT_SLOTS[$warlockLevel] ?? null;

                if ($pactData) {
                    return new PactSlotInfo(
                        count: $pactData['count'],
                        level: $pactData['level'],
                    );
                }
            }
        }

        return null;
    }
}
