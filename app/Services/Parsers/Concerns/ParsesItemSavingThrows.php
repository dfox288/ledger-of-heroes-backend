<?php

namespace App\Services\Parsers\Concerns;

use App\Models\Condition;
use Illuminate\Support\Collection;

trait ParsesItemSavingThrows
{
    private ?string $itemConditionRegexPattern = null;

    /**
     * Additional item-specific effects that aren't standard conditions but should trigger "negates".
     * Includes movement effects like forced/pushed.
     */
    private array $additionalItemNegateEffects = ['forced', 'pushed'];

    /**
     * Parse saving throw from item description
     *
     * Examples:
     * - "succeed on a DC 10 Charisma saving throw"
     *   -> dc:10, ability:'CHA', effect:'negates', is_initial_save:true
     *
     * - "make a DC 15 Dexterity saving throw or take 5d4 damage"
     *   -> dc:15, ability:'DEX', effect:'half_damage', is_initial_save:true
     *
     * - "DC 15 Dexterity saving throw, taking damage on failure or half damage on success"
     *   -> dc:15, ability:'DEX', effect:'half_damage', is_initial_save:true
     *
     * @param  string  $description  Full item description
     * @return array|null ['dc' => 10, 'ability_code' => 'CHA', 'save_effect' => 'negates', 'is_initial_save' => true]
     */
    protected function parseItemSavingThrow(string $description): ?array
    {
        // Pattern 1: "DC X [Ability] saving throw" or "DC X [Ability] save"
        // Supports both full names (Charisma) and abbreviations (CHA, DEX)
        if (! preg_match('/DC\s+(\d+)\s+(Strength|Dexterity|Constitution|Intelligence|Wisdom|Charisma|STR|DEX|CON|INT|WIS|CHA)\s+sav(?:ing\s+throw|e)/i', $description, $matches)) {
            return null;
        }

        $dc = (int) $matches[1];
        $abilityName = $matches[2];

        // Map ability name to code
        $abilityMap = [
            'strength' => 'STR',
            'dexterity' => 'DEX',
            'constitution' => 'CON',
            'intelligence' => 'INT',
            'wisdom' => 'WIS',
            'charisma' => 'CHA',
            // Support abbreviations
            'str' => 'STR',
            'dex' => 'DEX',
            'con' => 'CON',
            'int' => 'INT',
            'wis' => 'WIS',
            'cha' => 'CHA',
        ];

        $abilityCode = $abilityMap[strtolower($abilityName)] ?? strtoupper($abilityName);

        if (! $abilityCode) {
            return null;
        }

        // Detect save effect
        $saveEffect = $this->detectSaveEffect($description);

        return [
            'dc' => $dc,
            'ability_code' => $abilityCode,
            'save_effect' => $saveEffect,
            'is_initial_save' => true,
        ];
    }

    /**
     * Detect the effect of a successful saving throw
     *
     * @return string 'negates', 'half_damage', or 'none'
     */
    protected function detectSaveEffect(string $description): string
    {
        // Pattern 1: "half as much damage" or "half damage" or "half on success"
        if (preg_match('/half\s+(?:as\s+much\s+)?damage|half\s+on\s+success/i', $description)) {
            return 'half_damage';
        }

        // Pattern 2: "or take damage" without "half" usually means negates (no damage on save)
        // But if description has both "take" and "half", it's half_damage (handled above)
        if (preg_match('/(?:or\s+take|taking).*damage/i', $description)) {
            // Check if there's a "half" reference we missed
            if (preg_match('/half/i', $description)) {
                return 'half_damage';
            }

            return 'half_damage'; // Most damage saves are half damage in D&D 5e
        }

        // Pattern 3: "or be [condition]" = negates (avoid condition on save)
        // Uses dynamic condition list from database + additional item effects
        $conditionPattern = $this->getItemConditionRegexPattern();
        if (preg_match('/or\s+be\s+('.$conditionPattern.')/i', $description)) {
            return 'negates';
        }

        // Default: negates (most common for non-damage effects)
        return 'negates';
    }

    /**
     * Get the regex pattern for matching conditions in item descriptions.
     * Builds pattern from database conditions + additional item effects.
     * Falls back to hardcoded list if database not available.
     */
    protected function getItemConditionRegexPattern(): string
    {
        if ($this->itemConditionRegexPattern !== null) {
            return $this->itemConditionRegexPattern;
        }

        $conditions = $this->loadItemConditionsFromDatabase();

        if ($conditions->isEmpty()) {
            // Fallback for unit tests or when DB unavailable
            $conditions = collect([
                'charmed', 'frightened', 'paralyzed', 'stunned', 'poisoned',
                'restrained', 'blinded', 'deafened', 'petrified', 'incapacitated',
                'grappled', 'prone', 'unconscious', 'invisible',
            ]);
        }

        // Add item-specific effects (forced movement, etc.)
        $allEffects = $conditions->merge($this->additionalItemNegateEffects)->unique();

        $this->itemConditionRegexPattern = $allEffects->implode('|');

        return $this->itemConditionRegexPattern;
    }

    /**
     * Load condition slugs from database.
     * Returns empty collection if database unavailable.
     */
    protected function loadItemConditionsFromDatabase(): Collection
    {
        try {
            if (function_exists('app') && app()->bound('db')) {
                return Condition::pluck('slug');
            }
        } catch (\Exception $e) {
            // Database not available
        }

        return collect();
    }
}
