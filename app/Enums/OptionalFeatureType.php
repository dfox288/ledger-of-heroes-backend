<?php

namespace App\Enums;

enum OptionalFeatureType: string
{
    case ELDRITCH_INVOCATION = 'eldritch_invocation';
    case ELEMENTAL_DISCIPLINE = 'elemental_discipline';
    case MANEUVER = 'maneuver';
    case METAMAGIC = 'metamagic';
    case FIGHTING_STYLE = 'fighting_style';
    case ARTIFICER_INFUSION = 'artificer_infusion';
    case RUNE = 'rune';
    case ARCANE_SHOT = 'arcane_shot';

    public function label(): string
    {
        return match ($this) {
            self::ELDRITCH_INVOCATION => 'Eldritch Invocation',
            self::ELEMENTAL_DISCIPLINE => 'Elemental Discipline',
            self::MANEUVER => 'Maneuver',
            self::METAMAGIC => 'Metamagic',
            self::FIGHTING_STYLE => 'Fighting Style',
            self::ARTIFICER_INFUSION => 'Artificer Infusion',
            self::RUNE => 'Rune',
            self::ARCANE_SHOT => 'Arcane Shot',
        };
    }

    public function defaultClassName(): ?string
    {
        return match ($this) {
            self::ELDRITCH_INVOCATION => 'Warlock',
            self::ELEMENTAL_DISCIPLINE => 'Monk',
            self::MANEUVER => 'Fighter',
            self::METAMAGIC => 'Sorcerer',
            self::FIGHTING_STYLE => null,  // Multiple classes
            self::ARTIFICER_INFUSION => 'Artificer',
            self::RUNE => 'Fighter',
            self::ARCANE_SHOT => 'Fighter',
        };
    }

    public function defaultSubclassName(): ?string
    {
        return match ($this) {
            self::ELEMENTAL_DISCIPLINE => 'Way of the Four Elements',
            self::MANEUVER => 'Battle Master',
            self::RUNE => 'Rune Knight',
            self::ARCANE_SHOT => 'Arcane Archer',
            default => null,
        };
    }
}
