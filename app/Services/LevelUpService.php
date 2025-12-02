<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\LevelUpResult;
use App\Exceptions\IncompleteCharacterException;
use App\Exceptions\MaxLevelReachedException;
use App\Models\Character;
use App\Models\CharacterFeature;
use App\Models\ClassFeature;

class LevelUpService
{
    private const MAX_LEVEL = 20;

    private const ASI_LEVELS_STANDARD = [4, 8, 12, 16, 19];

    private const ASI_LEVELS_FIGHTER = [4, 6, 8, 12, 14, 16, 19];

    private const ASI_LEVELS_ROGUE = [4, 8, 10, 12, 16, 19];

    private CharacterStatCalculator $calculator;

    public function __construct(?CharacterStatCalculator $calculator = null)
    {
        $this->calculator = $calculator ?? new CharacterStatCalculator;
    }

    /**
     * Level up a character by one level.
     *
     * @throws MaxLevelReachedException
     * @throws IncompleteCharacterException
     */
    public function levelUp(Character $character): LevelUpResult
    {
        $this->validateCanLevelUp($character);

        $previousLevel = $character->level;
        $newLevel = $previousLevel + 1;

        $hpIncrease = $this->calculateHpIncrease($character);
        $featuresGained = $this->grantClassFeatures($character, $newLevel);
        $asiPending = $this->isAsiLevel($character, $newLevel);

        $character->level = $newLevel;
        $character->max_hit_points += $hpIncrease;
        $character->current_hit_points += $hpIncrease;

        if ($asiPending) {
            $character->asi_choices_remaining++;
        }

        $character->save();

        $spellSlots = $this->getSpellSlots($character);

        return new LevelUpResult(
            previousLevel: $previousLevel,
            newLevel: $newLevel,
            hpIncrease: $hpIncrease,
            newMaxHp: $character->max_hit_points,
            featuresGained: $featuresGained,
            spellSlots: $spellSlots,
            asiPending: $asiPending,
        );
    }

    /**
     * Calculate HP increase for level up.
     *
     * Uses average hit die + CON modifier (minimum 1 HP).
     */
    public function calculateHpIncrease(Character $character): int
    {
        $hitDie = $character->characterClass->effective_hit_die
            ?? $character->characterClass->hit_die;
        $averageRoll = (int) floor($hitDie / 2) + 1;
        $conModifier = $this->calculator->abilityModifier($character->constitution ?? 10);

        return max(1, $averageRoll + $conModifier);
    }

    /**
     * Grant class features for the new level.
     *
     * @return array<array{id: int, name: string, description: string|null}>
     */
    private function grantClassFeatures(Character $character, int $newLevel): array
    {
        $features = $character->characterClass->features()
            ->where('level', $newLevel)
            ->where('is_optional', false)
            ->get();

        $granted = [];
        foreach ($features as $feature) {
            CharacterFeature::create([
                'character_id' => $character->id,
                'feature_type' => ClassFeature::class,
                'feature_id' => $feature->id,
                'source' => 'class',
                'level_acquired' => $newLevel,
            ]);

            $granted[] = [
                'id' => $feature->id,
                'name' => $feature->feature_name,
                'description' => $feature->description,
            ];
        }

        return $granted;
    }

    /**
     * Check if the new level grants an Ability Score Improvement.
     */
    private function isAsiLevel(Character $character, int $level): bool
    {
        $classSlug = strtolower($character->characterClass->slug ?? '');

        $asiLevels = match ($classSlug) {
            'fighter' => self::ASI_LEVELS_FIGHTER,
            'rogue' => self::ASI_LEVELS_ROGUE,
            default => self::ASI_LEVELS_STANDARD,
        };

        return in_array($level, $asiLevels);
    }

    /**
     * Get current spell slots for the character's class and level.
     *
     * @return array<int, int>
     */
    private function getSpellSlots(Character $character): array
    {
        $classSlug = $character->characterClass->slug ?? '';

        return $this->calculator->getSpellSlots($classSlug, $character->level);
    }

    /**
     * Validate that the character can level up.
     *
     * @throws MaxLevelReachedException
     * @throws IncompleteCharacterException
     */
    private function validateCanLevelUp(Character $character): void
    {
        if ($character->level >= self::MAX_LEVEL) {
            throw new MaxLevelReachedException($character);
        }

        if (! $character->is_complete) {
            throw new IncompleteCharacterException($character);
        }
    }
}
