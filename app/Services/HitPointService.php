<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\CharacterClass;
use InvalidArgumentException;

class HitPointService
{
    public function __construct(
        private CharacterStatCalculator $calculator
    ) {}

    /**
     * Calculate starting HP for level 1 character.
     * Formula: Hit Die Maximum + CON Modifier
     */
    public function calculateStartingHp(Character $character, CharacterClass $class): int
    {
        $hitDieMax = $class->hit_die;
        $conMod = $this->calculator->abilityModifier($character->constitution ?? 10);

        // Minimum 1 HP even with negative CON
        return max(1, $hitDieMax + $conMod);
    }

    /**
     * Calculate average HP gain for a level-up.
     * Formula: (Hit Die / 2 + 1) + CON Modifier
     */
    public function calculateAverageHpGain(int $hitDie, int $constitution): int
    {
        $average = intdiv($hitDie, 2) + 1;
        $conMod = $this->calculator->abilityModifier($constitution);

        // Minimum 1 HP per level
        return max(1, $average + $conMod);
    }

    /**
     * Calculate HP gain from a roll.
     * Formula: Roll Result + CON Modifier
     */
    public function calculateRolledHpGain(int $rollResult, int $hitDie, int $constitution): int
    {
        if ($rollResult < 1 || $rollResult > $hitDie) {
            throw new InvalidArgumentException(
                "Roll result {$rollResult} is invalid for d{$hitDie} (must be 1-{$hitDie})"
            );
        }

        $conMod = $this->calculator->abilityModifier($constitution);

        // Minimum 1 HP per level
        return max(1, $rollResult + $conMod);
    }

    /**
     * Recalculate HP after CON change.
     * Adjusts HP by (new modifier - old modifier) * total level.
     *
     * @return array{adjustment: int, new_max_hp: int, new_current_hp: int}
     */
    public function recalculateForConChange(
        Character $character,
        int $oldConstitution,
        int $newConstitution
    ): array {
        $oldMod = $this->calculator->abilityModifier($oldConstitution);
        $newMod = $this->calculator->abilityModifier($newConstitution);
        $diff = $newMod - $oldMod;

        if ($diff === 0) {
            return [
                'adjustment' => 0,
                'new_max_hp' => $character->max_hit_points ?? 0,
                'new_current_hp' => $character->current_hit_points ?? 0,
            ];
        }

        $adjustment = $diff * $character->total_level;
        $newMaxHp = max(1, ($character->max_hit_points ?? 0) + $adjustment);

        // Current HP adjusts but caps at new max
        $newCurrentHp = $character->current_hit_points ?? 0;
        if ($adjustment > 0) {
            // CON increased: gain HP
            $newCurrentHp += $adjustment;
        } else {
            // CON decreased: lose HP but not below 1
            $newCurrentHp = max(1, min($newCurrentHp, $newMaxHp));
        }

        return [
            'adjustment' => $adjustment,
            'new_max_hp' => $newMaxHp,
            'new_current_hp' => $newCurrentHp,
        ];
    }

    /**
     * Get the hit die for a specific level (handles multiclass).
     */
    public function getHitDieForLevel(Character $character, int $level): int
    {
        // Get class pivots ordered by when they were added
        $pivots = $character->characterClasses()
            ->orderBy('order')
            ->get();

        $currentLevel = 0;
        foreach ($pivots as $pivot) {
            $classLevels = $pivot->level;
            if ($currentLevel + $classLevels >= $level) {
                // This class covers the requested level
                return $pivot->characterClass->hit_die;
            }
            $currentLevel += $classLevels;
        }

        // Fallback to primary class
        return $character->primaryClass?->hit_die ?? 8;
    }
}
