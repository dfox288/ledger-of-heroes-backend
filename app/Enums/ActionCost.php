<?php

namespace App\Enums;

/**
 * D&D 5e Action Economy types.
 *
 * Represents what action type a feature or ability requires to use.
 */
enum ActionCost: string
{
    case ACTION = 'action';
    case BONUS_ACTION = 'bonus_action';
    case REACTION = 'reaction';
    case FREE = 'free';
    case PASSIVE = 'passive';

    public function label(): string
    {
        return match ($this) {
            self::ACTION => 'Action',
            self::BONUS_ACTION => 'Bonus Action',
            self::REACTION => 'Reaction',
            self::FREE => 'Free',
            self::PASSIVE => 'Passive',
        };
    }

    /**
     * Parse action cost from a casting_time string.
     *
     * Examples:
     * - "1 action" → ACTION
     * - "1 bonus action" → BONUS_ACTION
     * - "1 reaction" → REACTION
     *
     * @return self|null Returns null if casting_time doesn't match known patterns
     */
    public static function fromCastingTime(?string $castingTime): ?self
    {
        if ($castingTime === null || $castingTime === '') {
            return null;
        }

        $lower = strtolower($castingTime);

        // Check bonus action first (it contains "action" so must be checked first)
        if (str_contains($lower, 'bonus action')) {
            return self::BONUS_ACTION;
        }

        if (str_contains($lower, 'reaction')) {
            return self::REACTION;
        }

        if (str_contains($lower, 'action')) {
            return self::ACTION;
        }

        // Longer casting times (1 minute, 1 hour, etc.) don't map to action economy
        return null;
    }
}
