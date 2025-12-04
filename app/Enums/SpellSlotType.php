<?php

namespace App\Enums;

enum SpellSlotType: string
{
    case STANDARD = 'standard';
    case PACT_MAGIC = 'pact_magic';

    public function label(): string
    {
        return match ($this) {
            self::STANDARD => 'Standard',
            self::PACT_MAGIC => 'Pact Magic',
        };
    }
}
