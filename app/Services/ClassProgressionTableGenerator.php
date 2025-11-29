<?php

namespace App\Services;

use App\Enums\DataTableType;
use App\Models\CharacterClass;
use Illuminate\Support\Collection;

final class ClassProgressionTableGenerator
{
    private const ORDINAL_SUFFIXES = ['1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th'];

    /**
     * Counters to exclude from the progression table.
     * These are either formula-based (don't scale with stored values)
     * or already represented in the Features column.
     */
    private const EXCLUDED_COUNTERS = [
        'Arcane Recovery',      // Wizard - formula: ceil(level/2)
        'Action Surge',         // Fighter - listed in Features column
        'Indomitable',          // Fighter - listed in Features column
        'Second Wind',          // Fighter - listed in Features column
        'Lay on Hands',         // Paladin - formula: level * 5
        'Channel Divinity',     // Paladin/Cleric - listed in Features column
        'Wholeness of Body',    // Monk L6 - one-time feature, not progression
        'Stroke of Luck',       // Rogue L20 - capstone feature, not progression
    ];

    /**
     * Synthetic progressions for classes where data is only in prose text
     * or where imported data is incorrect/incomplete.
     *
     * - Barbarian Rage Damage: specified in prose: "+2 at 1st, +3 at 9th, +4 at 16th"
     * - Rogue Sneak Attack: PHB p.96 formula ceil(level/2)d6, XML data has wrong level mappings
     */
    private const SYNTHETIC_PROGRESSIONS = [
        'barbarian' => [
            'rage_damage' => [
                'label' => 'Rage Damage',
                'type' => 'bonus',
                'values' => [1 => '+2', 9 => '+3', 16 => '+4'],
            ],
        ],
        'rogue' => [
            'sneak_attack' => [
                'label' => 'Sneak Attack',
                'type' => 'dice',
                // PHB p.96: Sneak Attack = ceil(level / 2) d6
                // Increases at odd levels: 1, 3, 5, 7, 9, 11, 13, 15, 17, 19
                'values' => [
                    1 => '1d6',
                    3 => '2d6',
                    5 => '3d6',
                    7 => '4d6',
                    9 => '5d6',
                    11 => '6d6',
                    13 => '7d6',
                    15 => '8d6',
                    17 => '9d6',
                    19 => '10d6',
                ],
            ],
        ],
    ];

    /**
     * Generate a complete progression table for a class.
     *
     * @return array{
     *   columns: array<array{key: string, label: string, type: string}>,
     *   rows: array<array<string, mixed>>
     * }
     */
    public function generate(CharacterClass $class): array
    {
        // Get the effective class for progression (parent for subclasses)
        $progressionClass = $class->is_base_class ? $class : $class->parentClass;

        if (! $progressionClass) {
            return ['columns' => [], 'rows' => []];
        }

        // Ensure relationships are loaded, including feature data tables
        $progressionClass->loadMissing(['levelProgression', 'counters', 'features.dataTables.entries']);

        // Get progression tables from feature data tables
        $featureProgressionTables = $this->getFeatureProgressionTables($progressionClass);

        $columns = $this->buildColumns($progressionClass, $featureProgressionTables);
        $rows = $this->buildRows($progressionClass, $columns, $featureProgressionTables);

        return [
            'columns' => $columns,
            'rows' => $rows,
        ];
    }

    /**
     * Get progression-related EntityDataTables from class features.
     *
     * Returns tables that have level-based entries (PROGRESSION or DAMAGE type).
     * DAMAGE tables from <roll> elements also contain level progression data.
     *
     * @return Collection<int, array{feature_name: string, table: \App\Models\EntityDataTable}>
     */
    private function getFeatureProgressionTables(CharacterClass $class): Collection
    {
        return $class->features
            ->flatMap(fn ($feature) => $feature->dataTables
                // Include both PROGRESSION (from text parsing) and DAMAGE (from <roll> elements)
                ->filter(fn ($table) => in_array($table->table_type, [DataTableType::PROGRESSION, DataTableType::DAMAGE]))
                // Only include if entries have level data
                ->filter(fn ($table) => $table->entries->contains(fn ($entry) => $entry->level !== null))
                ->map(fn ($table) => [
                    'feature_name' => $feature->feature_name,
                    'table' => $table,
                ])
            );
    }

    /**
     * Build dynamic columns based on class data.
     */
    private function buildColumns(CharacterClass $class, Collection $featureProgressionTables): array
    {
        $columns = [
            ['key' => 'level', 'label' => 'Level', 'type' => 'integer'],
            ['key' => 'proficiency_bonus', 'label' => 'Proficiency Bonus', 'type' => 'bonus'],
            ['key' => 'features', 'label' => 'Features', 'type' => 'string'],
        ];

        // Track which column keys we've already added (to avoid duplicates)
        $existingKeys = collect($columns)->pluck('key')->toArray();

        // Add columns from feature progression tables (EntityDataTable)
        // These take precedence over counters since they have actual level-based data
        foreach ($featureProgressionTables as $item) {
            $key = $this->slugify($item['feature_name']);

            if (! in_array($key, $existingKeys)) {
                $columns[] = [
                    'key' => $key,
                    'label' => $item['feature_name'],
                    'type' => 'dice', // Progression tables typically contain dice values
                ];
                $existingKeys[] = $key;
            }
        }

        // Add counter columns (Sneak Attack, Ki Points, Rage Damage, etc.)
        // Exclude formula-based or redundant counters
        // Also skip counters that have a corresponding EntityDataTable (prefer the table data)
        $counters = $class->counters->pluck('counter_name')->unique()->sort()
            ->reject(fn ($name) => in_array($name, self::EXCLUDED_COUNTERS))
            ->reject(fn ($name) => in_array($this->slugify($name), $existingKeys));

        foreach ($counters as $counterName) {
            $key = $this->slugify($counterName);
            $columns[] = [
                'key' => $key,
                'label' => $counterName,
                'type' => $this->getCounterType($counterName),
            ];
            $existingKeys[] = $key;
        }

        // Add synthetic progressions (e.g., Rage Damage for Barbarian)
        $syntheticProgs = self::SYNTHETIC_PROGRESSIONS[$class->slug] ?? [];
        foreach ($syntheticProgs as $key => $data) {
            if (! in_array($key, $existingKeys)) {
                $columns[] = [
                    'key' => $key,
                    'label' => $data['label'],
                    'type' => $data['type'],
                ];
                $existingKeys[] = $key;
            }
        }

        // Add spell slot columns if applicable
        $progression = $class->levelProgression;
        if ($progression->isNotEmpty()) {
            // Check for cantrips
            if ($progression->max('cantrips_known') > 0) {
                $columns[] = ['key' => 'cantrips_known', 'label' => 'Cantrips Known', 'type' => 'integer'];
            }

            // Check each spell slot level (using ordinal suffixes)
            foreach (self::ORDINAL_SUFFIXES as $index => $suffix) {
                $column = "spell_slots_{$suffix}";
                if ($progression->max($column) > 0) {
                    $columns[] = [
                        'key' => $column,
                        'label' => $this->ordinal($index + 1).' Level Slots',
                        'type' => 'integer',
                    ];
                }
            }
        }

        return $columns;
    }

    /**
     * Build rows for levels 1-20.
     */
    private function buildRows(CharacterClass $class, array $columns, Collection $featureProgressionTables): array
    {
        $rows = [];
        $features = $class->features->groupBy('level');
        $counters = $this->buildCounterLookup($class->counters);
        $progression = $class->levelProgression->keyBy('level');

        // Build lookup for feature progression table values
        $tableValues = $this->buildTableValueLookup($featureProgressionTables);

        for ($level = 1; $level <= 20; $level++) {
            $row = [
                'level' => $level,
                'proficiency_bonus' => CharacterClass::formattedProficiencyBonus($level),
                'features' => $this->getFeaturesForLevel($features, $level),
            ];

            // Add values from feature progression tables (EntityDataTable)
            foreach ($tableValues as $key => $values) {
                $row[$key] = $this->getTableValue($values, $level);
            }

            // Add counter values (interpolated) - only for keys not already set by tables
            foreach ($counters as $counterKey => $counterData) {
                if (! isset($row[$counterKey])) {
                    $row[$counterKey] = $this->getCounterValue($counterData, $level);
                }
            }

            // Add synthetic progression values (e.g., Rage Damage)
            $syntheticProgs = self::SYNTHETIC_PROGRESSIONS[$class->slug] ?? [];
            foreach ($syntheticProgs as $key => $data) {
                $row[$key] = $this->getSyntheticValue($data['values'], $level);
            }

            // Add spell slots from progression
            $prog = $progression->get($level);
            foreach ($columns as $col) {
                if (str_starts_with($col['key'], 'spell_slots_') || $col['key'] === 'cantrips_known') {
                    $row[$col['key']] = $prog?->{$col['key']} ?? 0;
                }
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Build lookup table for feature progression table values by level.
     *
     * @return array<string, array<int, string>> Key is column slug, value is level => value map
     */
    private function buildTableValueLookup(Collection $featureProgressionTables): array
    {
        $lookup = [];

        foreach ($featureProgressionTables as $item) {
            $key = $this->slugify($item['feature_name']);
            $table = $item['table'];

            // Build level => value map from table entries
            $values = [];
            foreach ($table->entries as $entry) {
                if ($entry->level !== null) {
                    $values[$entry->level] = $entry->result_text;
                }
            }

            // Only add if we have values
            if (! empty($values)) {
                $lookup[$key] = $values;
            }
        }

        return $lookup;
    }

    /**
     * Get interpolated table value for a level.
     *
     * Values are sparse (only defined at certain levels).
     * We find the most recent defined value at or before the given level.
     */
    private function getTableValue(array $values, int $level): string
    {
        // Find the most recent value at or before this level
        for ($l = $level; $l >= 1; $l--) {
            if (isset($values[$l])) {
                return $values[$l];
            }
        }

        return '—';
    }

    /**
     * Get synthetic progression value for a level.
     *
     * Like getTableValue, finds the most recent defined value at or before the given level.
     */
    private function getSyntheticValue(array $values, int $level): string
    {
        for ($l = $level; $l >= 1; $l--) {
            if (isset($values[$l])) {
                return $values[$l];
            }
        }

        return '—';
    }

    /**
     * Get features for a specific level as comma-separated string.
     * Excludes multiclass-only features and choice options from the progression table display.
     */
    private function getFeaturesForLevel(Collection $features, int $level): string
    {
        $levelFeatures = $features->get($level, collect())
            ->reject(fn ($feature) => $feature->is_multiclass_only)
            ->reject(fn ($feature) => $feature->parent_feature_id !== null);

        return $levelFeatures->pluck('feature_name')->join(', ') ?: '—';
    }

    /**
     * Build lookup table for counter values by level.
     */
    private function buildCounterLookup(Collection $counters): array
    {
        $lookup = [];

        // Filter out excluded counters
        $filteredCounters = $counters->reject(
            fn ($counter) => in_array($counter->counter_name, self::EXCLUDED_COUNTERS)
        );

        foreach ($filteredCounters->groupBy('counter_name') as $name => $values) {
            $key = $this->slugify($name);
            $lookup[$key] = [
                'name' => $name,
                'values' => $values->pluck('counter_value', 'level')->toArray(),
            ];
        }

        return $lookup;
    }

    /**
     * Get interpolated counter value for a level.
     *
     * Counters are often sparse (only defined at certain levels).
     * We find the most recent defined value at or before the given level.
     */
    private function getCounterValue(array $counterData, int $level): string
    {
        $values = $counterData['values'];
        $name = $counterData['name'];

        // Find the most recent value at or before this level
        $value = null;
        for ($l = $level; $l >= 1; $l--) {
            if (isset($values[$l])) {
                $value = $values[$l];
                break;
            }
        }

        if ($value === null) {
            return '—';
        }

        // Format based on counter type
        return $this->formatCounterValue($name, $value);
    }

    /**
     * Format counter value based on counter name.
     */
    private function formatCounterValue(string $name, int|string $value): string
    {
        $nameLower = strtolower($name);

        // Dice-based counters
        if (str_contains($nameLower, 'sneak attack')) {
            return "{$value}d6";
        }
        if (str_contains($nameLower, 'martial arts')) {
            return "1d{$value}";
        }

        return (string) $value;
    }

    /**
     * Get counter column type.
     */
    private function getCounterType(string $name): string
    {
        $nameLower = strtolower($name);
        if (str_contains($nameLower, 'sneak attack') || str_contains($nameLower, 'martial arts')) {
            return 'dice';
        }

        return 'integer';
    }

    /**
     * Convert string to slug format.
     */
    private function slugify(string $name): string
    {
        return strtolower(str_replace([' ', "'"], ['_', ''], $name));
    }

    /**
     * Get ordinal suffix for a number.
     */
    private function ordinal(int $number): string
    {
        $suffixes = ['th', 'st', 'nd', 'rd'];
        $mod = $number % 100;

        return $number.($suffixes[($mod - 20) % 10] ?? $suffixes[$mod] ?? $suffixes[0]);
    }
}
