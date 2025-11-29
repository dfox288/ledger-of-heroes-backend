<?php

namespace App\Enums;

enum DataTableType: string
{
    case RANDOM = 'random';
    case DAMAGE = 'damage';
    case MODIFIER = 'modifier';
    case LOOKUP = 'lookup';
    case PROGRESSION = 'progression';

    public function label(): string
    {
        return match ($this) {
            self::RANDOM => 'Random Table',
            self::DAMAGE => 'Damage Dice',
            self::MODIFIER => 'Modifier',
            self::LOOKUP => 'Lookup Table',
            self::PROGRESSION => 'Progression',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::RANDOM => 'Rollable tables with discrete outcomes (e.g., Personality Trait d8)',
            self::DAMAGE => 'Damage dice for features/spells (e.g., Necrotic Damage d12)',
            self::MODIFIER => 'Size/weight modifiers (e.g., Size Modifier 2d4)',
            self::LOOKUP => 'Reference tables without dice (e.g., Musical Instrument)',
            self::PROGRESSION => 'Level-based progressions (e.g., Bard Spells Known)',
        };
    }

    public function hasDice(): bool
    {
        return match ($this) {
            self::RANDOM, self::DAMAGE, self::MODIFIER => true,
            self::LOOKUP, self::PROGRESSION => false,
        };
    }
}
