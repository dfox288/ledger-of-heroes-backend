<?php

namespace App\Enums;

/**
 * Sources from which a character can acquire features, languages, proficiencies, etc.
 */
enum CharacterSource: string
{
    case RACE = 'race';
    case BACKGROUND = 'background';
    case CHARACTER_CLASS = 'class';
    case SUBCLASS_FEATURE = 'subclass_feature';
    case FEAT = 'feat';
    case ITEM = 'item';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::RACE => 'Race',
            self::BACKGROUND => 'Background',
            self::CHARACTER_CLASS => 'Class',
            self::SUBCLASS_FEATURE => 'Subclass Feature',
            self::FEAT => 'Feat',
            self::ITEM => 'Item',
            self::OTHER => 'Other',
        };
    }

    /**
     * Get sources valid for language acquisition.
     *
     * @return array<CharacterSource>
     */
    public static function forLanguages(): array
    {
        return [self::RACE, self::BACKGROUND, self::FEAT];
    }

    /**
     * Get sources valid for proficiency acquisition.
     *
     * @return array<CharacterSource>
     */
    public static function forProficiencies(): array
    {
        return [self::CHARACTER_CLASS, self::SUBCLASS_FEATURE, self::RACE, self::BACKGROUND];
    }

    /**
     * Get sources valid for spell acquisition.
     *
     * @return array<CharacterSource>
     */
    public static function forSpells(): array
    {
        return [self::CHARACTER_CLASS, self::SUBCLASS_FEATURE, self::RACE, self::FEAT, self::ITEM, self::OTHER];
    }

    /**
     * Get sources valid for feature acquisition.
     *
     * @return array<CharacterSource>
     */
    public static function forFeatures(): array
    {
        return [self::CHARACTER_CLASS, self::RACE, self::BACKGROUND];
    }

    /**
     * Get validation rule string for Laravel's 'in' rule.
     *
     * @param  array<CharacterSource>  $sources
     */
    public static function validationRule(array $sources): string
    {
        return 'in:'.implode(',', array_map(fn ($s) => $s->value, $sources));
    }
}
