<?php

namespace App\Enums;

/**
 * Party leveling mode - determines how characters gain levels.
 *
 * - milestone: DM manually triggers level-ups (default)
 * - xp: Characters level up automatically when XP thresholds are reached
 */
enum LevelingMode: string
{
    case MILESTONE = 'milestone';
    case XP = 'xp';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::MILESTONE => 'Milestone',
            self::XP => 'Experience Points',
        };
    }
}
