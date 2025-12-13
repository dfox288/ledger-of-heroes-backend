<?php

namespace App\Enums;

/**
 * Logic operators for multiclass requirement grouping.
 *
 * Used to indicate whether requirements in a group must ALL be met (AND)
 * or if ANY one requirement satisfies the group (OR).
 */
enum RequirementLogic: string
{
    case OR = 'OR';
    case AND = 'AND';

    public function label(): string
    {
        return match ($this) {
            self::OR => 'Any One',
            self::AND => 'All Required',
        };
    }
}
