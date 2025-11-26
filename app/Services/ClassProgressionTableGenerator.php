<?php

namespace App\Services;

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

        // Ensure relationships are loaded
        $progressionClass->loadMissing(['levelProgression', 'counters', 'features']);

        $columns = $this->buildColumns($progressionClass);
        $rows = $this->buildRows($progressionClass, $columns);

        return [
            'columns' => $columns,
            'rows' => $rows,
        ];
    }

    /**
     * Build dynamic columns based on class data.
     */
    private function buildColumns(CharacterClass $class): array
    {
        $columns = [
            ['key' => 'level', 'label' => 'Level', 'type' => 'integer'],
            ['key' => 'proficiency_bonus', 'label' => 'Proficiency Bonus', 'type' => 'bonus'],
            ['key' => 'features', 'label' => 'Features', 'type' => 'string'],
        ];

        // Add counter columns (Sneak Attack, Ki Points, Rage Damage, etc.)
        // Exclude formula-based or redundant counters
        $counters = $class->counters->pluck('counter_name')->unique()->sort()
            ->reject(fn ($name) => in_array($name, self::EXCLUDED_COUNTERS));
        foreach ($counters as $counterName) {
            $columns[] = [
                'key' => $this->slugify($counterName),
                'label' => $counterName,
                'type' => $this->getCounterType($counterName),
            ];
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
    private function buildRows(CharacterClass $class, array $columns): array
    {
        $rows = [];
        $features = $class->features->groupBy('level');
        $counters = $this->buildCounterLookup($class->counters);
        $progression = $class->levelProgression->keyBy('level');

        for ($level = 1; $level <= 20; $level++) {
            $row = [
                'level' => $level,
                'proficiency_bonus' => CharacterClass::formattedProficiencyBonus($level),
                'features' => $this->getFeaturesForLevel($features, $level),
            ];

            // Add counter values (interpolated)
            foreach ($counters as $counterKey => $counterData) {
                $row[$counterKey] = $this->getCounterValue($counterData, $level);
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
     * Get features for a specific level as comma-separated string.
     * Excludes multiclass-only features from the progression table display.
     */
    private function getFeaturesForLevel(Collection $features, int $level): string
    {
        $levelFeatures = $features->get($level, collect())
            ->reject(fn ($feature) => $feature->is_multiclass_only);

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
