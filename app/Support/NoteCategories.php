<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Helper class for note category constants and logic.
 *
 * Replaces the NoteCategory enum to allow free-form user-created categories.
 */
class NoteCategories
{
    public const PERSONALITY_TRAIT = 'personality_trait';

    public const IDEAL = 'ideal';

    public const BOND = 'bond';

    public const FLAW = 'flaw';

    public const BACKSTORY = 'backstory';

    public const CUSTOM = 'custom';

    /**
     * The default D&D character note categories.
     *
     * @var array<string>
     */
    public const DEFAULTS = [
        self::PERSONALITY_TRAIT,
        self::IDEAL,
        self::BOND,
        self::FLAW,
        self::BACKSTORY,
        self::CUSTOM,
    ];

    /**
     * Check if a category requires a title.
     *
     * Only backstory requires a title - all other categories (including custom
     * and user-created) have optional titles.
     */
    public static function requiresTitle(string $category): bool
    {
        return $category === self::BACKSTORY;
    }

    /**
     * Get the display label for a category.
     *
     * Returns human-readable labels for default categories.
     * For user-created categories, converts snake_case to Title Case.
     */
    public static function label(string $category): string
    {
        return match ($category) {
            self::PERSONALITY_TRAIT => 'Personality Trait',
            self::IDEAL => 'Ideal',
            self::BOND => 'Bond',
            self::FLAW => 'Flaw',
            self::BACKSTORY => 'Backstory',
            self::CUSTOM => 'Custom Note',
            default => Str::title(str_replace('_', ' ', $category)),
        };
    }

    /**
     * Check if a category is one of the default categories.
     */
    public static function isDefault(string $category): bool
    {
        return in_array($category, self::DEFAULTS, true);
    }
}
