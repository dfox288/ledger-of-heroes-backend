<?php

namespace App\Enums;

enum NoteCategory: string
{
    case PersonalityTrait = 'personality_trait';
    case Ideal = 'ideal';
    case Bond = 'bond';
    case Flaw = 'flaw';
    case Backstory = 'backstory';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::PersonalityTrait => 'Personality Trait',
            self::Ideal => 'Ideal',
            self::Bond => 'Bond',
            self::Flaw => 'Flaw',
            self::Backstory => 'Backstory',
            self::Custom => 'Custom Note',
        };
    }

    /**
     * Check if this category requires a title.
     */
    public function requiresTitle(): bool
    {
        return $this === self::Custom || $this === self::Backstory;
    }

    /**
     * Get all D&D character sheet categories (excluding custom).
     *
     * @return array<NoteCategory>
     */
    public static function characterSheetCategories(): array
    {
        return [
            self::PersonalityTrait,
            self::Ideal,
            self::Bond,
            self::Flaw,
            self::Backstory,
        ];
    }
}
