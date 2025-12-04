<?php

namespace App\Services\Parsers\Concerns;

/**
 * Trait for parsing optional feature choice progressions from ClassFeature descriptions.
 *
 * Extracts choice counts and level progressions for features like:
 * - Maneuvers (Battle Master)
 * - Metamagic (Sorcerer)
 * - Infusions (Artificer)
 * - Runes (Rune Knight)
 * - Arcane Shots (Arcane Archer)
 * - Elemental Disciplines (Way of the Four Elements)
 * - Fighting Styles (Multiple classes)
 *
 * Returns counter arrays compatible with class_counters table storage.
 */
trait ParsesFeatureChoiceProgressions
{
    use ConvertsWordNumbers;

    /**
     * Feature patterns that grant optional feature choices.
     * Maps feature name patterns to counter configuration.
     * Order matters - more specific patterns should come first!
     *
     * @var array<string, array{counter_name: string, subclass_pattern: ?string}>
     */
    protected array $featureChoicePatterns = [
        // More specific patterns first
        'Additional Arcane Shot' => [
            'counter_name' => 'Arcane Shots Known',
            'subclass_pattern' => 'Arcane Archer',
            'is_additional' => true,
        ],
        'Arcane Shot' => [
            'counter_name' => 'Arcane Shots Known',
            'subclass_pattern' => 'Arcane Archer',
        ],
        'Additional Maneuvers' => [
            'counter_name' => 'Maneuvers Known',
            'subclass_pattern' => 'Battle Master',
            'is_additional' => true,
        ],
        'Combat Superiority' => [
            'counter_name' => 'Maneuvers Known',
            'subclass_pattern' => 'Battle Master',
        ],
        'Extra Elemental Discipline' => [
            'counter_name' => 'Elemental Disciplines Known',
            'subclass_pattern' => 'Way of the Four Elements',
            'is_additional' => true,
        ],
        'Disciple of the Elements' => [
            'counter_name' => 'Elemental Disciplines Known',
            'subclass_pattern' => 'Way of the Four Elements',
        ],
        'Additional Fighting Style' => [
            'counter_name' => 'Fighting Styles Known',
            'subclass_pattern' => null,
            'is_additional' => true,
        ],
        'Fighting Style' => [
            'counter_name' => 'Fighting Styles Known',
            'subclass_pattern' => null,
        ],
        'Metamagic' => [
            'counter_name' => 'Metamagic Known',
            'subclass_pattern' => null,
        ],
        'Infuse Item' => [
            'counter_name' => 'Infusions Known',
            'subclass_pattern' => null,
        ],
        'Rune Carver' => [
            'counter_name' => 'Runes Known',
            'subclass_pattern' => 'Rune Knight',
        ],
    ];

    /**
     * Parse feature choice progressions from class features.
     *
     * @param  array<int, array<string, mixed>>  $features  Array of parsed features
     * @return array<int, array<string, mixed>> Counter arrays for class_counters table
     */
    public function parseFeatureChoiceProgressions(array $features): array
    {
        $counters = [];
        $progressionState = []; // Track running totals for each counter type
        $seenCounters = []; // Track (name, level, subclass) to avoid duplicates

        foreach ($features as $feature) {
            $featureName = $feature['name'] ?? '';
            $level = $feature['level'] ?? 0;
            $description = $feature['description'] ?? '';

            // Find matching pattern
            $patternConfig = $this->findMatchingFeaturePattern($featureName);
            if ($patternConfig === null) {
                continue;
            }

            $counterName = $patternConfig['counter_name'];
            $isAdditional = $patternConfig['is_additional'] ?? false;
            $subclass = $this->extractSubclassFromFeatureName($featureName) ?? $patternConfig['subclass_pattern'];

            // Try to extract counts from the description
            $extractedCounters = $this->extractCountersFromDescription(
                $description,
                $level,
                $counterName,
                $subclass,
                $isAdditional,
                $progressionState[$counterName] ?? 0
            );

            if (! empty($extractedCounters)) {
                foreach ($extractedCounters as $counter) {
                    // Create unique key for deduplication
                    $key = sprintf('%s|%d|%s', $counter['name'], $counter['level'], $counter['subclass'] ?? '');

                    // Skip if we already have a counter for this (name, level, subclass)
                    if (isset($seenCounters[$key])) {
                        continue;
                    }

                    $seenCounters[$key] = true;
                    $counters[] = $counter;

                    // Update progression state with the highest value at each level
                    $progressionState[$counterName] = max(
                        $progressionState[$counterName] ?? 0,
                        $counter['value']
                    );
                }
            }
        }

        return $counters;
    }

    /**
     * Find a matching feature pattern configuration.
     *
     * @return array<string, mixed>|null
     */
    protected function findMatchingFeaturePattern(string $featureName): ?array
    {
        foreach ($this->featureChoicePatterns as $pattern => $config) {
            if (str_contains($featureName, $pattern)) {
                return $config;
            }
        }

        return null;
    }

    /**
     * Extract subclass name from feature name parentheses.
     * e.g., "Combat Superiority (Battle Master)" -> "Battle Master"
     */
    protected function extractSubclassFromFeatureName(string $featureName): ?string
    {
        if (preg_match('/\(([^)]+)\)/', $featureName, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Extract counter values from feature description.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function extractCountersFromDescription(
        string $description,
        int $baseLevel,
        string $counterName,
        ?string $subclass,
        bool $isAdditional,
        int $currentTotal
    ): array {
        $counters = [];

        // Try embedded table first (most reliable)
        $tableCounters = $this->parseEmbeddedTable($description, $counterName, $subclass);
        if (! empty($tableCounters)) {
            return $tableCounters;
        }

        // Try "should know X" pattern (Elemental Disciplines)
        if (preg_match('/should know\s+(\d+)\s+/i', $description, $matches)) {
            return [[
                'level' => $baseLevel,
                'name' => $counterName,
                'value' => (int) $matches[1],
                'reset_timing' => null,
                'subclass' => $subclass,
            ]];
        }

        // For additional features, increment from current total
        if ($isAdditional) {
            // Look for "an additional" or "another" pattern
            $additionalCount = 1; // Default to 1 additional
            if (preg_match('/(?:learn|gain)\s+(one|two|three|four|five)\s+additional/i', $description, $matches)) {
                $additionalCount = $this->wordToNumber($matches[1]);
            } elseif (preg_match('/(two|three)\s+additional/i', $description, $matches)) {
                $additionalCount = $this->wordToNumber($matches[1]);
            }

            // Handle "a second option" pattern (Fighting Style Champion)
            if (preg_match('/(?:choose|can choose)\s+a\s+second/i', $description)) {
                // "a second" means total becomes 2, regardless of current
                return [[
                    'level' => $baseLevel,
                    'name' => $counterName,
                    'value' => 2,
                    'reset_timing' => null,
                    'subclass' => $subclass,
                ]];
            }

            return [[
                'level' => $baseLevel,
                'name' => $counterName,
                'value' => $currentTotal + $additionalCount,
                'reset_timing' => null,
                'subclass' => $subclass,
            ]];
        }

        // Try natural language pattern for initial + additional at levels
        $initialCount = $this->parseInitialCount($description);
        if ($initialCount > 0) {
            // Add initial counter
            $counters[] = [
                'level' => $baseLevel,
                'name' => $counterName,
                'value' => $initialCount,
                'reset_timing' => null,
                'subclass' => $subclass,
            ];

            // Look for additional at levels
            $additionalLevels = $this->parseAdditionalLevels($description);
            $additionalPerLevel = $this->parseAdditionalCount($description);

            $runningTotal = $initialCount;
            foreach ($additionalLevels as $level) {
                $runningTotal += $additionalPerLevel;
                $counters[] = [
                    'level' => $level,
                    'name' => $counterName,
                    'value' => $runningTotal,
                    'reset_timing' => null,
                    'subclass' => $subclass,
                ];
            }
        }

        // Handle "Choose one" for Fighting Style
        if (empty($counters) && preg_match('/choose\s+one\s+of\s+the\s+following/i', $description)) {
            $counters[] = [
                'level' => $baseLevel,
                'name' => $counterName,
                'value' => 1,
                'reset_timing' => null,
                'subclass' => $subclass,
            ];
        }

        // Handle "one other... of your choice" for initial single choice
        if (empty($counters) && preg_match('/one\s+(?:other\s+)?(?:\w+\s+)?(?:discipline|style|option)\s+of\s+your\s+choice/i', $description)) {
            $counters[] = [
                'level' => $baseLevel,
                'name' => $counterName,
                'value' => 1,
                'reset_timing' => null,
                'subclass' => $subclass,
            ];
        }

        return $counters;
    }

    /**
     * Parse embedded tables like "Level | Known | Active" or "Fighter Level | Number of Runes"
     *
     * @return array<int, array<string, mixed>>
     */
    protected function parseEmbeddedTable(string $description, string $counterName, ?string $subclass): array
    {
        $counters = [];

        // Pattern for table rows: "2nd | 4" or "2nd | 4 | 2" or "3rd | 2"
        // Requires start of line or whitespace before the ordinal
        // This avoids matching ranges like "5th-8th | 3" where 8th follows a hyphen
        if (preg_match_all('/(?:^|\s)(\d+)(?:st|nd|rd|th)\s*\|\s*(\d+)(?:\s*\|\s*\d+)?/m', $description, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $counters[] = [
                    'level' => (int) $match[1],
                    'name' => $counterName,
                    'value' => (int) $match[2],
                    'reset_timing' => null,
                    'subclass' => $subclass,
                ];
            }
        }

        return $counters;
    }

    /**
     * Parse initial count from description.
     * e.g., "learn three maneuvers" -> 3
     */
    protected function parseInitialCount(string $description): int
    {
        // Pattern: "learn/gain/choose/pick X (feature type)"
        if (preg_match('/(?:learn|gain|choose|pick)\s+(one|two|three|four|five|six|seven|eight|nine|ten)\b/i', $description, $matches)) {
            return $this->wordToNumber($matches[1]);
        }

        return 0;
    }

    /**
     * Parse additional count per level from description.
     * e.g., "two additional maneuvers" -> 2
     */
    protected function parseAdditionalCount(string $description): int
    {
        // Pattern: "X additional" or "another one"
        if (preg_match('/(one|two|three|four|five)\s+additional/i', $description, $matches)) {
            return $this->wordToNumber($matches[1]);
        }

        if (preg_match('/another\s+(one|two|three)/i', $description, $matches)) {
            return $this->wordToNumber($matches[1]);
        }

        // Default: "another one" or "an additional"
        if (preg_match('/another\s+one|an\s+additional/i', $description)) {
            return 1;
        }

        return 1; // Default to 1 if we found "additional" but couldn't parse count
    }

    /**
     * Parse levels where additional choices are gained.
     * e.g., "at 7th, 10th, and 15th level" -> [7, 10, 15]
     *
     * @return array<int>
     */
    protected function parseAdditionalLevels(string $description): array
    {
        // Look for "at Xth, Yth, and Zth level" pattern
        if (preg_match('/(?:additional|another)[^.]*?at\s+([\d,\s]+(?:st|nd|rd|th)[^.]*)/i', $description, $matches)) {
            return $this->extractLevelsFromText($matches[1]);
        }

        return [];
    }

    /**
     * Extract level numbers from text containing ordinals.
     * e.g., "7th, 10th, and 15th level" -> [7, 10, 15]
     *
     * @return array<int>
     */
    public function extractLevelsFromText(string $text): array
    {
        $levels = [];

        if (preg_match_all('/(\d+)(?:st|nd|rd|th)/i', $text, $matches)) {
            $levels = array_map('intval', $matches[1]);
        }

        return $levels;
    }
}
