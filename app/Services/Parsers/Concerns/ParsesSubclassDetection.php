<?php

namespace App\Services\Parsers\Concerns;

/**
 * Detects subclasses from class features and counters.
 *
 * Identifies subclass patterns from:
 * - "Martial Archetype: Battle Master" style features
 * - "Feature Name (Subclass Name)" parenthetical patterns
 * - Explicit <subclass> tags in counters
 */
trait ParsesSubclassDetection
{
    /**
     * Detect subclasses from features and counters.
     *
     * Returns both the detected subclasses AND filtered base class features.
     * Base class features will have subclass-specific features removed.
     *
     * @param  array<int, array<string, mixed>>  $features
     * @param  array<int, array<string, mixed>>  $counters
     * @return array{subclasses: array, filtered_base_features: array, archetype: string|null}
     */
    private function detectSubclasses(array $features, array $counters, array $optionalSpellData = []): array
    {
        $subclasses = [];
        $subclassNames = [];
        $archetype = null;

        // Pattern 1: "Martial Archetype: Battle Master" or "Otherworldly Patron: The Fiend"
        // Pattern 2: "Combat Superiority (Battle Master)" - name with parentheses
        foreach ($features as $feature) {
            $name = $feature['name'];

            // Pattern 1: "Martial Archetype: Subclass Name" or "Primal Path: Subclass Name"
            // Capture group 1 = archetype name, group 2 = subclass name
            if (preg_match('/^(Martial Archetype|Primal Path|Monastic Tradition|Otherworldly Patron|Divine Domain|Arcane Tradition|Sacred Oath|Ranger Archetype|Roguish Archetype|Sorcerous Origin|Bard College|Druid Circle|College of|Artificer Specialist):\s*(.+)$/i', $name, $matches)) {
                // Extract archetype name (only set once - first match wins)
                if ($archetype === null) {
                    $archetype = trim($matches[1]);
                }
                $subclassNames[] = trim($matches[2]);
            }

            // Pattern 2: "Feature Name (Subclass Name)"
            if (preg_match('/\(([^)]+)\)$/', $name, $matches)) {
                $possibleSubclass = trim($matches[1]);

                // Define false positive patterns that should NOT be treated as subclasses
                $falsePositivePatterns = [
                    '/^CR\s+\d+/',                   // CR 1, CR 2, CR 3, CR 4
                    '/^CR\s+\d+\/\d+/',              // CR 1/2, CR 3/4
                    '/^\d+\s*\/\s*(rest|day)/i',    // 2/rest, 3/day
                    '/^\d+(st|nd|rd|th)\b/i',        // 2nd, 3rd, 4th
                    '/\buses?\b/i',                  // one use, two uses
                    '/^\d+\s+slots?/i',              // 2 slots
                    '/^level\s+\d+/i',               // level 5
                    '/^\d+\s+times?/i',              // 2 times
                ];

                // Check if this matches any false positive pattern
                $isFalsePositive = false;
                foreach ($falsePositivePatterns as $pattern) {
                    if (preg_match($pattern, $possibleSubclass)) {
                        $isFalsePositive = true;
                        break;
                    }
                }

                // Only consider it a subclass if it:
                // 1. Not a false positive pattern (CR, uses, etc.)
                // 2. Not a common qualifier like "Revised" or "Alternative"
                // 3. Not a number (like "Action Surge (2)")
                // 4. Not a lowercase phrase (like "two uses")
                // 5. Starts with a capital letter (subclass names are proper nouns)
                if (! $isFalsePositive
                    && ! in_array(strtolower($possibleSubclass), ['revised', 'alternative', 'optional', 'variant'])
                    && ! is_numeric($possibleSubclass)
                    && preg_match('/^[A-Z]/', $possibleSubclass)
                    && ! preg_match('/^\d+/', $possibleSubclass)) {
                    $subclassNames[] = $possibleSubclass;
                }
            }
        }

        // Pattern 3: Direct <subclass> tag in counters
        foreach ($counters as $counter) {
            if (! empty($counter['subclass'])) {
                $subclassNames[] = $counter['subclass'];
            }
        }

        // Remove duplicates and sort
        $subclassNames = array_unique($subclassNames);
        sort($subclassNames);

        // Track which feature indices belong to subclasses
        $subclassFeatureIndices = [];

        // Group features and counters by subclass
        foreach ($subclassNames as $subclassName) {
            $subclassFeatures = [];
            $subclassCounters = [];

            // Find features belonging to this subclass
            foreach ($features as $index => $feature) {
                $name = $feature['name'];

                // Check if feature name contains subclass name
                if ($this->featureBelongsToSubclass($name, $subclassName)) {
                    $subclassFeatures[] = $feature;
                    $subclassFeatureIndices[] = $index; // Track this index for removal from base
                }
            }

            // Find counters belonging to this subclass
            foreach ($counters as $counter) {
                if (! empty($counter['subclass']) && $counter['subclass'] === $subclassName) {
                    $subclassCounters[] = $counter;
                }
            }

            $subclass = [
                'name' => $subclassName,
                'features' => $subclassFeatures,
                'counters' => $subclassCounters,
            ];

            // Add spell progression if this subclass has optional spellcasting
            if (isset($optionalSpellData[$subclassName])) {
                $spellData = $optionalSpellData[$subclassName];
                $subclass['spell_progression'] = $spellData['spell_progression'];
                if ($spellData['spellcasting_ability']) {
                    $subclass['spellcasting_ability'] = $spellData['spellcasting_ability'];
                }
            }

            $subclasses[] = $subclass;
        }

        // Filter base class features - remove any that belong to subclasses
        $baseFeatures = [];
        foreach ($features as $index => $feature) {
            if (! in_array($index, $subclassFeatureIndices)) {
                $baseFeatures[] = $feature;
            }
        }

        return [
            'subclasses' => $subclasses,
            'filtered_base_features' => $baseFeatures,
            'archetype' => $archetype,
        ];
    }

    /**
     * Check if a feature belongs to a specific subclass based on naming patterns.
     *
     * Only uses explicit naming patterns to avoid false positives:
     * - Pattern 1: "Archetype: Subclass Name" (intro feature)
     * - Pattern 2: "Feature Name (Subclass Name)" (subsequent features)
     *
     * NOTE: We intentionally do NOT use str_contains() because subclass names
     * can be substrings of other feature names. For example, "Thief" is a substring
     * of "Spell Thief (Arcane Trickster)", which would incorrectly assign that
     * Arcane Trickster feature to the Thief subclass.
     *
     * @param  string  $featureName  The feature name to check
     * @param  string  $subclassName  The subclass name to match against
     */
    private function featureBelongsToSubclass(string $featureName, string $subclassName): bool
    {
        // Pattern 1: "Archetype: Subclass Name" (intro feature)
        if (preg_match('/^(?:Martial Archetype|Primal Path|Monastic Tradition|Otherworldly Patron|Divine Domain|Arcane Tradition|Sacred Oath|Ranger Archetype|Roguish Archetype|Sorcerous Origin|Bard College|Druid Circle|College of|Artificer Specialist):\s*'.preg_quote($subclassName, '/').'$/i', $featureName)) {
            return true;
        }

        // Pattern 2: "Feature Name (Subclass Name)" (subsequent features)
        // The subclass name must be at the END of the feature name, in parentheses
        if (preg_match('/\('.preg_quote($subclassName, '/').'\)$/i', $featureName)) {
            return true;
        }

        return false;
    }
}
