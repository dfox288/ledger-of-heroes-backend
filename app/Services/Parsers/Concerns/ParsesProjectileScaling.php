<?php

namespace App\Services\Parsers\Concerns;

/**
 * Trait for parsing projectile/target scaling from spell "At Higher Levels" text.
 *
 * Handles patterns like:
 * - "creates one more dart for each slot level above 1st" (Magic Missile)
 * - "you create one additional ray for each slot level above 2nd" (Scorching Ray)
 * - "you can target one additional creature for each slot level above 2nd" (Hold Person)
 *
 * Used by: SpellXmlParser, SpellImporter
 */
trait ParsesProjectileScaling
{
    /**
     * Known base projectile counts for specific spells.
     * These can't be reliably extracted from text.
     */
    protected static array $knownBaseProjectiles = [
        'dart' => 3,  // Magic Missile: 3 darts at 1st level
        'ray' => 3,   // Scorching Ray: 3 rays at 2nd level
        'beam' => 1,  // Eldritch Blast: 1 beam at base (scales by character level)
        'bolt' => 3,  // Generic bolt spells
        'missile' => 3,
    ];

    /**
     * Parse projectile scaling information from higher_levels text.
     *
     * @param  string|null  $higherLevels  The "At Higher Levels" text
     * @param  int  $spellLevel  The base spell level
     * @return array{projectile_count: int, projectile_per_level: int, projectile_name: string}|null
     */
    protected function parseProjectileScaling(?string $higherLevels, int $spellLevel): ?array
    {
        if (empty($higherLevels)) {
            return null;
        }

        // Pattern 1: "creates/create [one|two|X] more/additional [projectile] for each slot level"
        // Matches: "creates one more dart", "create one additional ray", "creates two more bolts"
        $pattern = '/(?:creates?|you create)\s+(\w+)\s+(?:more|additional)\s+(\w+)\s+for each (?:slot )?level/i';

        if (preg_match($pattern, $higherLevels, $matches)) {
            $countWord = strtolower($matches[1]);
            $projectileName = $this->singularize(strtolower($matches[2]));

            // Convert word to number
            $perLevel = $this->wordToNumber($countWord);

            // Get base count from known values or default to 1
            $baseCount = self::$knownBaseProjectiles[$projectileName] ?? 1;

            return [
                'projectile_count' => $baseCount,
                'projectile_per_level' => $perLevel,
                'projectile_name' => $projectileName,
            ];
        }

        // Pattern 2: "target [one|two|X] additional/more creature(s) for each"
        // Matches: "target one additional creature", "target two more creatures"
        $targetPattern = '/(?:can )?target\s+(\w+)\s+(?:additional|more)\s+creatures?\s+for each/i';

        if (preg_match($targetPattern, $higherLevels, $matches)) {
            $countWord = strtolower($matches[1]);
            $perLevel = $this->wordToNumber($countWord);

            return [
                'projectile_count' => 1, // Most target spells start with 1 target
                'projectile_per_level' => $perLevel,
                'projectile_name' => 'target',
            ];
        }

        return null;
    }

    /**
     * Parse character-level beam scaling from spell description.
     *
     * Handles patterns like Eldritch Blast:
     * "two beams at 5th level, three beams at 11th level, and four beams at 17th level"
     *
     * @param  string  $description  The spell description text
     * @return array{projectile_count: int, projectile_per_level: int, projectile_name: string}|null
     */
    protected function parseCharacterLevelBeamScaling(string $description): ?array
    {
        // Pattern: "two beams at 5th level, three beams at 11th level"
        if (preg_match('/(\w+)\s+beams?\s+at\s+5th\s+level.*?(\w+)\s+beams?\s+at\s+11th\s+level/i', $description, $matches)) {
            return [
                'projectile_count' => 1, // 1 beam at base level
                'projectile_per_level' => 1, // Not really per-level, but indicates scaling exists
                'projectile_name' => 'beam',
            ];
        }

        return null;
    }

    /**
     * Convert word numbers to integers.
     */
    private function wordToNumber(string $word): int
    {
        $word = strtolower(trim($word));

        $numbers = [
            'one' => 1,
            'two' => 2,
            'three' => 3,
            'four' => 4,
            'five' => 5,
            'six' => 6,
            'an' => 1,
            'a' => 1,
        ];

        if (isset($numbers[$word])) {
            return $numbers[$word];
        }

        // Try numeric
        if (is_numeric($word)) {
            return (int) $word;
        }

        return 1; // Default
    }

    /**
     * Simple singularization for common projectile names.
     */
    private function singularize(string $word): string
    {
        // Handle common plural forms
        if (str_ends_with($word, 's') && ! str_ends_with($word, 'ss')) {
            return rtrim($word, 's');
        }

        return $word;
    }
}
