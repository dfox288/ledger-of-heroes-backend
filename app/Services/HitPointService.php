<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\Feat;
use App\Models\Modifier;
use App\Models\Race;
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

    /**
     * Get the HP per level bonus from feats.
     *
     * Sums all hit_points_per_level modifiers from feats the character has.
     * Used for feats like Tough that grant +2 HP per level.
     */
    public function getFeatHpBonus(Character $character): int
    {
        // Get all feat IDs the character has
        $featIds = $character->features()
            ->where('feature_type', Feat::class)
            ->pluck('feature_id')
            ->toArray();

        if (empty($featIds)) {
            return 0;
        }

        // Sum all hit_points_per_level modifiers from those feats
        return (int) Modifier::where('reference_type', Feat::class)
            ->whereIn('reference_id', $featIds)
            ->where('modifier_category', 'hit_points_per_level')
            ->sum('value');
    }

    /**
     * Get the HP per level bonus from race.
     *
     * Sums all hp modifiers from the character's race and parent race (if subrace).
     * Used for races like Hill Dwarf that grant +1 HP per level (Dwarven Toughness).
     */
    public function getRaceHpBonus(Character $character): int
    {
        return $this->getRaceHpBonusBySlug($character->race_slug);
    }

    /**
     * Get the HP per level bonus for a specific race (by slug).
     *
     * Looks up hp modifiers for a race and its parent race (if subrace).
     * Used by getRaceHpBonus() and recalculateForRaceChange().
     */
    private function getRaceHpBonusBySlug(?string $raceSlug): int
    {
        if (! $raceSlug) {
            return 0;
        }

        $race = Race::where('slug', $raceSlug)->first();
        if (! $race) {
            return 0;
        }

        // Collect race IDs to check (race + parent race if exists)
        $raceIds = [$race->id];
        if ($race->parent_race_id) {
            $raceIds[] = $race->parent_race_id;
        }

        return (int) Modifier::where('reference_type', Race::class)
            ->whereIn('reference_id', $raceIds)
            ->where('modifier_category', 'hp')
            ->sum('value');
    }

    /**
     * Recalculate HP after race change.
     * Adjusts HP by (new race bonus - old race bonus) * total level.
     *
     * @return array{adjustment: int, new_max_hp: int, new_current_hp: int}
     */
    public function recalculateForRaceChange(
        Character $character,
        ?string $oldRaceSlug,
        ?string $newRaceSlug
    ): array {
        // Guard: no adjustment if character has no levels
        if ($character->total_level === 0) {
            return [
                'adjustment' => 0,
                'new_max_hp' => $character->max_hit_points ?? 0,
                'new_current_hp' => $character->current_hit_points ?? 0,
            ];
        }

        $oldBonus = $this->getRaceHpBonusBySlug($oldRaceSlug);
        $newBonus = $this->getRaceHpBonusBySlug($newRaceSlug);
        $diff = $newBonus - $oldBonus;

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
            // Race bonus increased: gain HP
            $newCurrentHp += $adjustment;
        } else {
            // Race bonus decreased: lose HP but cap at new max and min 1
            $newCurrentHp = max(1, min($newCurrentHp, $newMaxHp));
        }

        return [
            'adjustment' => $adjustment,
            'new_max_hp' => $newMaxHp,
            'new_current_hp' => $newCurrentHp,
        ];
    }

    /**
     * Modify character HP with D&D rules enforcement.
     *
     * Handles damage, healing, and temp HP with proper D&D 5e rules:
     * - Damage: Subtracts from temp HP first, overflow to current HP
     * - Healing: Adds to current HP, caps at max HP
     * - Set: Sets current HP directly, caps at max HP
     * - Temp HP: Higher-wins logic (doesn't stack)
     * - Death saves reset when HP goes from 0 to positive
     *
     * @param  array{type: string, value: int}|null  $hpChange  Parsed HP change (type: damage/heal/set)
     * @param  int|null  $tempHp  New temp HP value (absolute, higher-wins applied)
     * @return array{current_hit_points: int, max_hit_points: int, temp_hit_points: int, death_save_successes: int, death_save_failures: int}
     */
    public function modifyHp(Character $character, ?array $hpChange, ?int $tempHp): array
    {
        $wasAtZeroHp = $character->current_hit_points === 0;
        $currentHp = $character->current_hit_points ?? 0;
        $maxHp = $character->max_hit_points ?? 0;
        $currentTempHp = $character->temp_hit_points ?? 0;
        $deathSaveSuccesses = $character->death_save_successes ?? 0;
        $deathSaveFailures = $character->death_save_failures ?? 0;

        // Process HP change if provided
        if ($hpChange !== null) {
            switch ($hpChange['type']) {
                case 'damage':
                    [$currentHp, $currentTempHp] = $this->applyDamage(
                        $hpChange['value'],
                        $currentHp,
                        $currentTempHp
                    );
                    break;

                case 'heal':
                    $currentHp = $this->applyHealing(
                        $hpChange['value'],
                        $currentHp,
                        $maxHp
                    );
                    break;

                case 'set':
                    $currentHp = min($hpChange['value'], $maxHp);
                    break;
            }
        }

        // Process temp HP if provided (higher-wins logic, except 0 always clears)
        if ($tempHp !== null) {
            if ($tempHp === 0) {
                $currentTempHp = 0;
            } else {
                $currentTempHp = max($currentTempHp, $tempHp);
            }
        }

        // Reset death saves if HP goes from 0 to positive
        if ($wasAtZeroHp && $currentHp > 0) {
            $deathSaveSuccesses = 0;
            $deathSaveFailures = 0;
        }

        // Persist changes
        $character->update([
            'current_hit_points' => $currentHp,
            'temp_hit_points' => $currentTempHp,
            'death_save_successes' => $deathSaveSuccesses,
            'death_save_failures' => $deathSaveFailures,
        ]);

        return [
            'current_hit_points' => $currentHp,
            'max_hit_points' => $maxHp,
            'temp_hit_points' => $currentTempHp,
            'death_save_successes' => $deathSaveSuccesses,
            'death_save_failures' => $deathSaveFailures,
        ];
    }

    /**
     * Apply damage with temp HP absorption.
     *
     * @return array{0: int, 1: int} [currentHp, tempHp]
     */
    private function applyDamage(int $damage, int $currentHp, int $tempHp): array
    {
        // Temp HP absorbs damage first
        if ($tempHp > 0) {
            if ($damage <= $tempHp) {
                // Temp HP absorbs all damage
                return [$currentHp, $tempHp - $damage];
            }

            // Temp HP absorbs partial, overflow to current HP
            $overflow = $damage - $tempHp;

            return [max(0, $currentHp - $overflow), 0];
        }

        // No temp HP, damage goes directly to current HP
        return [max(0, $currentHp - $damage), 0];
    }

    /**
     * Apply healing with max HP cap.
     */
    private function applyHealing(int $healing, int $currentHp, int $maxHp): int
    {
        return min($currentHp + $healing, $maxHp);
    }
}
