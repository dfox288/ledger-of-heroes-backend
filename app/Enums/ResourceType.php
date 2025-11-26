<?php

namespace App\Enums;

enum ResourceType: string
{
    case KI_POINTS = 'ki_points';
    case SORCERY_POINTS = 'sorcery_points';
    case SUPERIORITY_DIE = 'superiority_die';
    case CHARGES = 'charges';
    case SPELL_SLOT = 'spell_slot';

    public function label(): string
    {
        return match ($this) {
            self::KI_POINTS => 'Ki Points',
            self::SORCERY_POINTS => 'Sorcery Points',
            self::SUPERIORITY_DIE => 'Superiority Die',
            self::CHARGES => 'Charges',
            self::SPELL_SLOT => 'Spell Slot',
        };
    }
}
