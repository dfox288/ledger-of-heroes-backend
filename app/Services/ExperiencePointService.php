<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Service for D&D 5e experience point calculations.
 *
 * Handles XP-to-level conversion, progress tracking, and threshold lookups
 * based on the standard D&D 5e XP progression table.
 */
class ExperiencePointService
{
    /**
     * D&D 5e XP thresholds for each level.
     *
     * Key = level, Value = minimum XP required to reach that level.
     * Level 1 starts at 0 XP.
     */
    private const XP_THRESHOLDS = [
        1 => 0,
        2 => 300,
        3 => 900,
        4 => 2700,
        5 => 6500,
        6 => 14000,
        7 => 23000,
        8 => 34000,
        9 => 48000,
        10 => 64000,
        11 => 85000,
        12 => 100000,
        13 => 120000,
        14 => 140000,
        15 => 165000,
        16 => 195000,
        17 => 225000,
        18 => 265000,
        19 => 305000,
        20 => 355000,
    ];

    private const MAX_LEVEL = 20;

    /**
     * Get the character level for a given XP amount.
     *
     * @param  int  $xp  Total experience points
     * @return int Level (1-20)
     */
    public function getLevelForXp(int $xp): int
    {
        $level = 1;

        foreach (self::XP_THRESHOLDS as $lvl => $threshold) {
            if ($xp >= $threshold) {
                $level = $lvl;
            } else {
                break;
            }
        }

        return min($level, self::MAX_LEVEL);
    }

    /**
     * Get the XP threshold required to reach a specific level.
     *
     * @param  int  $level  Target level (1-20)
     * @return int|null XP required, or null if level is invalid
     */
    public function getXpForLevel(int $level): ?int
    {
        if ($level < 1 || $level > self::MAX_LEVEL) {
            return null;
        }

        return self::XP_THRESHOLDS[$level];
    }

    /**
     * Calculate XP remaining until the next level.
     *
     * @param  int  $currentXp  Current experience points
     * @return int XP needed for next level (0 if at max level)
     */
    public function getXpToNextLevel(int $currentXp): int
    {
        $currentLevel = $this->getLevelForXp($currentXp);

        if ($currentLevel >= self::MAX_LEVEL) {
            return 0;
        }

        $nextLevelXp = self::XP_THRESHOLDS[$currentLevel + 1];

        return $nextLevelXp - $currentXp;
    }

    /**
     * Calculate progress percentage toward the next level.
     *
     * @param  int  $currentXp  Current experience points
     * @return float Progress percentage (0.0-100.0)
     */
    public function getXpProgressPercent(int $currentXp): float
    {
        $currentLevel = $this->getLevelForXp($currentXp);

        if ($currentLevel >= self::MAX_LEVEL) {
            return 100.0;
        }

        $currentLevelXp = self::XP_THRESHOLDS[$currentLevel];
        $nextLevelXp = self::XP_THRESHOLDS[$currentLevel + 1];
        $xpInCurrentLevel = $currentXp - $currentLevelXp;
        $xpNeededForLevel = $nextLevelXp - $currentLevelXp;

        if ($xpNeededForLevel === 0) {
            return 0.0;
        }

        return round(($xpInCurrentLevel / $xpNeededForLevel) * 100, 1);
    }

    /**
     * Get the XP thresholds table.
     *
     * @return array<int, int> Level => XP threshold
     */
    public function getXpThresholds(): array
    {
        return self::XP_THRESHOLDS;
    }
}
