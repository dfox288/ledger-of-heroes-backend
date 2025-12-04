<?php

namespace App\Services;

use App\Exceptions\InsufficientHitDiceException;
use App\Models\Character;

class HitDiceService
{
    /**
     * Get hit dice grouped by die type with totals.
     *
     * @return array{hit_dice: array<string, array{available: int, max: int, spent: int}>, total: array{available: int, max: int, spent: int}}
     */
    public function getHitDice(Character $character): array
    {
        $character->loadMissing('characterClasses.characterClass');

        $hitDice = [];
        $totalAvailable = 0;
        $totalMax = 0;
        $totalSpent = 0;

        foreach ($character->characterClasses as $classPivot) {
            $dieType = 'd'.$classPivot->characterClass->hit_die;
            $max = $classPivot->level;
            $spent = $classPivot->hit_dice_spent;
            $available = $max - $spent;

            if (! isset($hitDice[$dieType])) {
                $hitDice[$dieType] = ['available' => 0, 'max' => 0, 'spent' => 0];
            }

            $hitDice[$dieType]['available'] += $available;
            $hitDice[$dieType]['max'] += $max;
            $hitDice[$dieType]['spent'] += $spent;

            $totalAvailable += $available;
            $totalMax += $max;
            $totalSpent += $spent;
        }

        // Sort by die size descending (d12, d10, d8, d6)
        krsort($hitDice);

        return [
            'hit_dice' => $hitDice,
            'total' => [
                'available' => $totalAvailable,
                'max' => $totalMax,
                'spent' => $totalSpent,
            ],
        ];
    }

    /**
     * Spend hit dice of a specific type.
     *
     * @return array{hit_dice: array, total: array}
     *
     * @throws InsufficientHitDiceException
     */
    public function spend(Character $character, string $dieType, int $quantity): array
    {
        $character->loadMissing('characterClasses.characterClass');

        // Find classes with the matching die type
        $matchingPivots = $character->characterClasses->filter(
            fn ($pivot) => 'd'.$pivot->characterClass->hit_die === $dieType
        );

        if ($matchingPivots->isEmpty()) {
            throw new InsufficientHitDiceException($dieType, 0, $quantity);
        }

        // Calculate total available of this die type
        $totalAvailable = $matchingPivots->sum(fn ($pivot) => $pivot->level - $pivot->hit_dice_spent);

        if ($totalAvailable < $quantity) {
            throw new InsufficientHitDiceException($dieType, $totalAvailable, $quantity);
        }

        // Spend from matching classes (first one that has available dice)
        $remaining = $quantity;
        foreach ($matchingPivots as $pivot) {
            $available = $pivot->level - $pivot->hit_dice_spent;

            if ($available > 0 && $remaining > 0) {
                $toSpend = min($available, $remaining);
                $pivot->hit_dice_spent += $toSpend;
                $pivot->save();
                $remaining -= $toSpend;
            }

            if ($remaining === 0) {
                break;
            }
        }

        return $this->getHitDice($character->fresh());
    }

    /**
     * Recover spent hit dice.
     *
     * If quantity is null, recovers half of total max (minimum 1) per D&D 5e rules.
     * Recovers larger dice first to maximize healing potential.
     *
     * @return array{recovered: int, hit_dice: array, total: array}
     */
    public function recover(Character $character, ?int $quantity = null): array
    {
        $character->loadMissing('characterClasses.characterClass');

        // Calculate totals
        $totalMax = $character->characterClasses->sum('level');
        $totalSpent = $character->characterClasses->sum('hit_dice_spent');

        // Determine how many to recover
        if ($quantity === null) {
            $quantity = max(1, (int) floor($totalMax / 2));
        }

        // Can't recover more than spent
        $toRecover = min($quantity, $totalSpent);

        if ($toRecover === 0) {
            return array_merge(['recovered' => 0], $this->getHitDice($character));
        }

        // Sort by die size descending (recover larger dice first)
        $sortedPivots = $character->characterClasses->sortByDesc(
            fn ($pivot) => $pivot->characterClass->hit_die
        );

        $remaining = $toRecover;
        foreach ($sortedPivots as $pivot) {
            if ($pivot->hit_dice_spent > 0 && $remaining > 0) {
                $canRecover = min($pivot->hit_dice_spent, $remaining);
                $pivot->hit_dice_spent -= $canRecover;
                $pivot->save();
                $remaining -= $canRecover;
            }

            if ($remaining === 0) {
                break;
            }
        }

        return array_merge(
            ['recovered' => $toRecover],
            $this->getHitDice($character->fresh())
        );
    }
}
