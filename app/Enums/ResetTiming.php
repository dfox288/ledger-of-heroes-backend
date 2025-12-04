<?php

namespace App\Enums;

enum ResetTiming: string
{
    case SHORT_REST = 'short_rest';
    case LONG_REST = 'long_rest';
    case DAWN = 'dawn';

    public function label(): string
    {
        return match ($this) {
            self::SHORT_REST => 'Short Rest',
            self::LONG_REST => 'Long Rest',
            self::DAWN => 'Dawn',
        };
    }
}
