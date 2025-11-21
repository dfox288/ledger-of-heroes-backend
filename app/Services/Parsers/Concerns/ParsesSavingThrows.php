<?php

namespace App\Services\Parsers\Concerns;

/**
 * Trait for parsing saving throw requirements from entity descriptions.
 *
 * Handles patterns like:
 * - "must succeed on a Dexterity saving throw"
 * - "make a Wisdom saving throw or be charmed"
 * - "makes all Wisdom saving throws with advantage" (buff)
 * - "make this saving throw with disadvantage" (debuff)
 *
 * Used by: Spells, Items (Wand of Fireballs), Monsters, etc.
 */
trait ParsesSavingThrows
{
    /**
     * Extract saving throw requirements from spell description.
     *
     * Patterns matched:
     * - "must succeed on a Dexterity saving throw"
     * - "must make a Wisdom saving throw"
     * - "A target must succeed on a Constitution saving throw or take damage"
     * - "succeed on a Strength saving throw at the end of each of its turns"
     * - "makes all Wisdom saving throws with advantage" (buff)
     * - "make this saving throw with disadvantage" (debuff)
     *
     * @param  string  $description  Full spell description text
     * @return array<int, array{ability: string, effect: string|null, recurring: bool, modifier: string|null}>
     */
    protected function parseSavingThrows(string $description): array
    {
        $savingThrows = [];
        $abilities = ['Strength', 'Dexterity', 'Constitution', 'Intelligence', 'Wisdom', 'Charisma'];

        foreach ($abilities as $ability) {
            // Pattern: "{Ability} saving throw" (case-insensitive)
            $pattern = '/('.$ability.')\s+saving\s+throw/i';

            if (preg_match_all($pattern, $description, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    // Extract context around the match
                    $matchPosition = $match[1];
                    $contextStart = max(0, $matchPosition - 100);

                    // Narrow context for recurring detection (before and slightly after match)
                    $recurringContext = substr($description, $contextStart, $matchPosition - $contextStart + 50);

                    // Check for advantage/disadvantage modifiers
                    // Look within ~80 chars before and after the match
                    $modifierContextLength = 80;
                    $modifierContext = substr($description, max(0, $matchPosition - $modifierContextLength), $modifierContextLength * 2);
                    $modifier = $this->determineSaveModifier($modifierContext);

                    // Determine if this is a recurring save (end of turn, etc.)
                    // Only look at text BEFORE and immediately AFTER the match, not far forward
                    $recurring = (
                        stripos($recurringContext, 'at the end of each of its turns') !== false ||
                        stripos($recurringContext, 'on each of your turns') !== false ||
                        stripos($recurringContext, 'end of each turn') !== false ||
                        stripos($recurringContext, 'repeat the save') !== false ||
                        stripos($recurringContext, 'can repeat') !== false ||
                        stripos($recurringContext, 'can make another') !== false ||
                        stripos($recurringContext, 'make another') !== false ||
                        stripos($recurringContext, 'each time') !== false
                    );

                    // Use different context window for effect detection based on save type
                    // Initial saves: need enough lookahead for damage description (200 chars)
                    // Recurring saves: need enough to find "end the effect" (250 chars)
                    $effectContextLength = $recurring ? 250 : 200;
                    $effectContext = substr($description, $contextStart, $effectContextLength);

                    // Determine save effect by analyzing context
                    $effect = $this->determineSaveEffect($effectContext);

                    $savingThrows[] = [
                        'ability' => $ability,
                        'effect' => $effect,
                        'recurring' => $recurring,
                        'modifier' => $modifier,
                    ];
                }
            }
        }

        // Remove duplicates (same ability + recurring state + modifier)
        $unique = [];
        foreach ($savingThrows as $save) {
            $key = $save['ability'].($save['recurring'] ? '_recurring' : '_initial').($save['modifier'] ?? '_none');
            if (! isset($unique[$key])) {
                $unique[$key] = $save;
            }
        }

        return array_values($unique);
    }

    /**
     * Determine if the saving throw has advantage or disadvantage.
     *
     * Patterns matched:
     * - "makes all Wisdom saving throws with advantage"
     * - "advantage on Intelligence, Wisdom, and Charisma saving throws"
     * - "make this saving throw with disadvantage"
     * - "does so with advantage if..." (conditional - treated as disadvantage for enemy)
     *
     * @param  string  $context  Text surrounding the saving throw mention
     * @return string 'none', 'advantage', 'disadvantage'
     */
    protected function determineSaveModifier(string $context): string
    {
        $lowerContext = strtolower($context);

        // Check for advantage patterns
        // Pattern 1: "makes/make [all] [ability] saving throws with advantage"
        // Pattern 2: "advantage on [ability] saving throws" (with word boundary to avoid matching "disadvantage")
        // Pattern 3: "with advantage" near "saving throw" (within 20 chars)
        if (
            preg_match('/makes?\s+(all\s+)?.*saving\s+throws?\s+with\s+advantage\b/i', $context) ||
            preg_match('/\badvantage\s+on.{0,50}?saving\s+throws?/i', $context) ||  // \b = word boundary, won't match "disadvantage"
            (preg_match('/saving\s+throws?.{0,20}with\s+advantage\b/i', $context) && ! preg_match('/does\s+so\s+with\s+advantage\s+if/i', $context))
        ) {
            return 'advantage';
        }

        // Check for disadvantage patterns
        // Pattern 1: "make this saving throw with disadvantage"
        // Pattern 2: "disadvantage on [ability] saving throws" (limited to 50 chars between)
        // Pattern 3: "does so with advantage if..." (conditional advantage = enemy has advantage situationally)
        if (
            preg_match('/makes?\s+(this\s+)?.*saving\s+throws?\s+with\s+disadvantage/i', $context) ||
            preg_match('/disadvantage\s+on.{0,50}?saving\s+throws?/i', $context) ||  // Non-greedy with max 50 chars
            preg_match('/does\s+so\s+with\s+advantage\s+if/i', $context)  // Enemy gets advantage conditionally
        ) {
            return 'disadvantage';
        }

        // Default: standard save requirement (no modifier)
        return 'none';
    }

    /**
     * Determine what happens on a successful save based on context.
     *
     * @param  string  $context  Text surrounding the saving throw mention
     * @return string|null Effect type or null if undetermined
     */
    protected function determineSaveEffect(string $context): ?string
    {
        $lowerContext = strtolower($context);

        // Check for ends effect FIRST (highest priority for recurring saves)
        // Phrases like "to end the effect" or "end the condition"
        if (
            preg_match('/to\s+end\s+(the\s+)?(effect|condition)/i', $context) ||
            preg_match('/end\s+(this\s+)?(effect|condition)/i', $context)
        ) {
            return 'ends_effect';
        }

        // Check for half damage BEFORE full damage (must be checked first!)
        // Most common in AoE spells
        if (
            // "takes half damage" or "take 1/2 damage"
            preg_match('/\b(take|takes?)\s+(half|1\/2)/i', $context) ||
            // "half damage" or "half the damage"
            preg_match('/half\s+(the\s+|as\s+much\s+)?damage/i', $context) ||
            // "or take 8d6 damage" (implies half on save)
            preg_match('/or\s+takes?\s+.*?damage/i', $context) ||
            // "on a successful save" phrase (very common in D&D)
            preg_match('/on\s+a\s+successful\s+(one|save)/i', $context)
        ) {
            return 'half_damage';
        }

        // Check for FULL damage (save or take full damage, not half)
        // Pattern: "on a failed save, takes Xd8 damage" OR "takes Xd8 damage on a failed save"
        // IMPORTANT: Must come AFTER half_damage check!
        if (
            preg_match('/on\s+a\s+failed\s+save.*takes?\s+\d+d\d+/i', $context) ||
            preg_match('/takes?\s+\d+d\d+.*on\s+a\s+failed\s+save/i', $context)
        ) {
            return 'full_damage';
        }

        // Check for negates (save completely negates effect)
        // Expanded to include "become/becomes" in addition to "be"
        if (
            preg_match('/or\s+(be|become|becomes?)\s+(charmed|frightened|paralyzed|stunned|poisoned|restrained|blinded|deafened|petrified|banished|incapacitated|cursed)/i', $context) ||
            stripos($lowerContext, 'negates') !== false ||
            stripos($lowerContext, 'avoids') !== false
        ) {
            return 'negates';
        }

        // Check for other "end" phrases (lower priority fallback)
        if (
            stripos($lowerContext, 'end') !== false &&
            (stripos($lowerContext, 'effect') !== false || stripos($lowerContext, 'condition') !== false)
        ) {
            return 'ends_effect';
        }

        // Check for reduced duration
        if (
            stripos($lowerContext, 'duration') !== false &&
            (stripos($lowerContext, 'reduced') !== false || stripos($lowerContext, 'shorter') !== false)
        ) {
            return 'reduced_duration';
        }

        // If we can't determine, return null
        return null;
    }
}
