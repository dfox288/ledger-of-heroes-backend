<?php

namespace App\Services;

use InvalidArgumentException;

class AbilityScoreValidatorService
{
    /**
     * D&D 5e Standard Array values.
     */
    public const STANDARD_ARRAY = [15, 14, 13, 12, 10, 8];

    /**
     * Point buy costs per score (PHB rules).
     */
    public const POINT_BUY_COSTS = [
        8 => 0,
        9 => 1,
        10 => 2,
        11 => 3,
        12 => 4,
        13 => 5,
        14 => 7,
        15 => 9,
    ];

    /**
     * Total points available for point buy.
     */
    public const POINT_BUY_BUDGET = 27;

    /**
     * Required ability score keys.
     */
    public const REQUIRED_ABILITIES = ['STR', 'DEX', 'CON', 'INT', 'WIS', 'CHA'];

    /**
     * Get the point buy cost for a single score.
     *
     * @throws InvalidArgumentException If score is outside 8-15 range
     */
    public function getPointBuyCost(int $score): int
    {
        if (! isset(self::POINT_BUY_COSTS[$score])) {
            throw new InvalidArgumentException(
                "Score {$score} is invalid for point buy. Must be 8-15."
            );
        }

        return self::POINT_BUY_COSTS[$score];
    }

    /**
     * Calculate total point buy cost for a set of scores.
     *
     * @param  array<string, int>  $scores  Associative array of ability => score
     *
     * @throws InvalidArgumentException If any score is outside 8-15 range
     */
    public function calculateTotalCost(array $scores): int
    {
        $total = 0;

        foreach ($scores as $score) {
            $total += $this->getPointBuyCost($score);
        }

        return $total;
    }

    /**
     * Validate that scores follow point buy rules.
     *
     * @param  array<string, int>  $scores  Associative array of ability => score
     */
    public function validatePointBuy(array $scores): bool
    {
        // Must have exactly 6 abilities
        if (! $this->hasExactlyAllAbilities($scores)) {
            return false;
        }

        // All scores must be 8-15
        foreach ($scores as $score) {
            if ($score < 8 || $score > 15) {
                return false;
            }
        }

        // Must spend exactly 27 points
        try {
            $total = $this->calculateTotalCost($scores);

            return $total === self::POINT_BUY_BUDGET;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Validate that scores match the standard array.
     *
     * @param  array<string, int>  $scores  Associative array of ability => score
     */
    public function validateStandardArray(array $scores): bool
    {
        // Must have exactly 6 abilities
        if (! $this->hasExactlyAllAbilities($scores)) {
            return false;
        }

        // Sort both arrays and compare
        $values = array_values($scores);
        sort($values);

        $standard = self::STANDARD_ARRAY;
        sort($standard);

        return $values === $standard;
    }

    /**
     * Get validation errors for point buy scores.
     *
     * @param  array<string, int>  $scores
     * @return array<string>
     */
    public function getPointBuyErrors(array $scores): array
    {
        $errors = [];

        // Check for missing abilities
        if (! $this->hasExactlyAllAbilities($scores)) {
            $missing = array_diff(self::REQUIRED_ABILITIES, array_keys($scores));
            if (! empty($missing)) {
                $errors[] = 'Missing ability scores: '.implode(', ', $missing);
            }
            if (count($scores) > 6) {
                $errors[] = 'Too many ability scores provided. Expected exactly 6.';
            }

            return $errors;
        }

        // Check score range
        foreach ($scores as $ability => $score) {
            if ($score < 8) {
                $errors[] = "{$ability} score {$score} is below minimum 8 for point buy.";
            } elseif ($score > 15) {
                $errors[] = "{$ability} score {$score} is above maximum 15 for point buy.";
            }
        }

        if (! empty($errors)) {
            return $errors;
        }

        // Check total cost
        try {
            $total = $this->calculateTotalCost($scores);
            if ($total !== self::POINT_BUY_BUDGET) {
                $difference = $total - self::POINT_BUY_BUDGET;
                $direction = $difference > 0 ? 'over' : 'under';
                $errors[] = "Point buy uses {$total} points ({$direction} by ".abs($difference).'). Must use exactly '.self::POINT_BUY_BUDGET.' points.';
            }
        } catch (InvalidArgumentException $e) {
            $errors[] = $e->getMessage();
        }

        return $errors;
    }

    /**
     * Get validation errors for standard array scores.
     *
     * @param  array<string, int>  $scores
     * @return array<string>
     */
    public function getStandardArrayErrors(array $scores): array
    {
        $errors = [];

        // Check for missing/extra abilities
        if (! $this->hasExactlyAllAbilities($scores)) {
            $missing = array_diff(self::REQUIRED_ABILITIES, array_keys($scores));
            if (! empty($missing)) {
                $errors[] = 'Missing ability scores: '.implode(', ', $missing);
            }
            if (count($scores) > 6) {
                $errors[] = 'Too many ability scores provided. Expected exactly 6.';
            }

            return $errors;
        }

        // Check values match standard array
        $values = array_values($scores);
        sort($values);

        $standard = self::STANDARD_ARRAY;
        sort($standard);

        if ($values !== $standard) {
            $errors[] = 'Scores must be exactly [15, 14, 13, 12, 10, 8] assigned to different abilities.';
        }

        return $errors;
    }

    /**
     * Check if exactly all 6 required abilities are present.
     *
     * @param  array<string, int>  $scores
     */
    private function hasExactlyAllAbilities(array $scores): bool
    {
        if (count($scores) !== 6) {
            return false;
        }

        foreach (self::REQUIRED_ABILITIES as $ability) {
            if (! array_key_exists($ability, $scores)) {
                return false;
            }
        }

        return true;
    }
}
