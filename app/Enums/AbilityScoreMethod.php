<?php

namespace App\Enums;

enum AbilityScoreMethod: string
{
    case Manual = 'manual';
    case PointBuy = 'point_buy';
    case StandardArray = 'standard_array';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::PointBuy => 'Point Buy',
            self::StandardArray => 'Standard Array',
        };
    }
}
