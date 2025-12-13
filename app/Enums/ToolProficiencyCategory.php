<?php

namespace App\Enums;

/**
 * Categories for tool proficiency choices.
 *
 * Used when a class or background grants a choice of tool proficiency
 * from a specific category (e.g., "any artisan's tools").
 */
enum ToolProficiencyCategory: string
{
    case ARTISAN = 'artisan';
    case MUSICAL_INSTRUMENT = 'musical_instrument';
    case GAMING = 'gaming';

    public function label(): string
    {
        return match ($this) {
            self::ARTISAN => "Artisan's Tools",
            self::MUSICAL_INSTRUMENT => 'Musical Instrument',
            self::GAMING => 'Gaming Set',
        };
    }
}
