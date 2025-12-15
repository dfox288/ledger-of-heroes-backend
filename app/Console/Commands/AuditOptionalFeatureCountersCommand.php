<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CharacterClass;
use App\Models\ClassCounter;
use Illuminate\Console\Command;

/**
 * Audit database counters against official D&D 5e rules.
 *
 * Compares class_counters table values against config/dnd-rules.php
 * reference data. Reports mismatches for investigation and fix.
 *
 * @example php artisan audit:optional-feature-counters
 */
class AuditOptionalFeatureCountersCommand extends Command
{
    protected $signature = 'audit:optional-feature-counters
        {--detailed : Show detailed output for each check}';

    protected $description = 'Audit optional feature counters against official D&D 5e rules';

    /**
     * @var array<int, array{feature: string, level: int, expected: int, actual: int|string, class: string, subclass: string|null, counter_name: string|null}>
     */
    private array $issues = [];

    private int $checksPerformed = 0;

    public function handle(): int
    {
        $this->info('Auditing Optional Feature Counters');
        $this->info('===================================');
        $this->newLine();

        $config = config('dnd-rules.optional_features');

        if (empty($config)) {
            $this->error('No optional_features config found in config/dnd-rules.php');

            return Command::FAILURE;
        }

        foreach ($config as $featureType => $spec) {
            $this->auditFeatureType($featureType, $spec);
        }

        $this->displayResults();

        return empty($this->issues) ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Audit a single feature type against its progression.
     *
     * @param  array{name: string, class: string, subclass: string|null, counter_names: array<string>, progression: array<int, int>}  $spec
     */
    private function auditFeatureType(string $featureType, array $spec): void
    {
        if ($this->option('detailed')) {
            $this->line("Checking {$spec['name']}...");
        }

        // Find the class
        $class = CharacterClass::where('slug', $spec['class'])->first();
        if (! $class) {
            $this->issues[] = [
                'feature' => $featureType,
                'level' => 0,
                'expected' => 0,
                'actual' => 'CLASS NOT FOUND',
                'class' => $spec['class'],
                'subclass' => $spec['subclass'],
                'counter_name' => null,
            ];

            return;
        }

        // Find subclass if specified
        $subclass = null;
        $targetClassId = $class->id;

        if ($spec['subclass']) {
            $subclass = CharacterClass::where('slug', $spec['subclass'])->first();
            if (! $subclass) {
                $this->issues[] = [
                    'feature' => $featureType,
                    'level' => 0,
                    'expected' => 0,
                    'actual' => 'SUBCLASS NOT FOUND',
                    'class' => $spec['class'],
                    'subclass' => $spec['subclass'],
                    'counter_name' => null,
                ];

                return;
            }
            $targetClassId = $subclass->id;
        }

        // Check each level in the progression
        foreach ($spec['progression'] as $level => $expectedCount) {
            $this->checksPerformed++;

            $actual = $this->getCounterValue(
                $targetClassId,
                $spec['counter_names'],
                $level
            );

            if ($actual !== $expectedCount) {
                $this->issues[] = [
                    'feature' => $featureType,
                    'level' => $level,
                    'expected' => $expectedCount,
                    'actual' => $actual ?? 'NOT FOUND',
                    'class' => $spec['class'],
                    'subclass' => $spec['subclass'],
                    'counter_name' => $this->findMatchingCounterName($targetClassId, $spec['counter_names']),
                ];
            } elseif ($this->option('detailed')) {
                $this->line("  ✓ Level {$level}: {$expectedCount}");
            }
        }
    }

    /**
     * Get the counter value for a class at a specific level.
     *
     * Checks multiple possible counter names (handles inconsistencies
     * like "Eldritch Invocations Known" vs "Eldritch Invocations").
     *
     * @param  array<string>  $counterNames
     */
    private function getCounterValue(int $classId, array $counterNames, int $level): ?int
    {
        // Try each possible counter name
        foreach ($counterNames as $counterName) {
            $counter = ClassCounter::where('class_id', $classId)
                ->where('counter_name', $counterName)
                ->where('level', $level)
                ->first();

            if ($counter) {
                return $counter->counter_value;
            }
        }

        // Also check if there's a counter at a lower level that applies
        // (counters may not have entries for every level)
        foreach ($counterNames as $counterName) {
            $counter = ClassCounter::where('class_id', $classId)
                ->where('counter_name', $counterName)
                ->where('level', '<=', $level)
                ->orderBy('level', 'desc')
                ->first();

            if ($counter && $counter->level === $level) {
                return $counter->counter_value;
            }
        }

        return null;
    }

    /**
     * Find which counter name is actually used in the database.
     *
     * @param  array<string>  $counterNames
     */
    private function findMatchingCounterName(int $classId, array $counterNames): ?string
    {
        foreach ($counterNames as $counterName) {
            $exists = ClassCounter::where('class_id', $classId)
                ->where('counter_name', $counterName)
                ->exists();

            if ($exists) {
                return $counterName;
            }
        }

        return null;
    }

    /**
     * Display audit results.
     */
    private function displayResults(): void
    {
        $this->newLine();
        $this->info("Checks performed: {$this->checksPerformed}");

        if (empty($this->issues)) {
            $this->newLine();
            $this->info('✓ All counters match official D&D 5e rules');

            return;
        }

        $this->newLine();
        $this->error('Found '.count($this->issues).' mismatch(es):');
        $this->newLine();

        $this->table(
            ['Feature', 'Level', 'Expected', 'Actual', 'Class', 'Subclass', 'Counter Name'],
            array_map(fn ($issue) => [
                $issue['feature'],
                $issue['level'],
                $issue['expected'],
                $issue['actual'],
                $issue['class'],
                $issue['subclass'] ?? '-',
                $issue['counter_name'] ?? 'N/A',
            ], $this->issues)
        );

        $this->newLine();
        $this->warn('Create GitHub issues to fix importer/parser code for each mismatch.');
        $this->warn('Do NOT manually patch the database - fix at the source.');
    }
}
