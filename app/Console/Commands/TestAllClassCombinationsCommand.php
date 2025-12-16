<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Character;
use App\Services\ClassSubclassAudit\SystematicFlowRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Test wizard flow and level-up for every class/subclass combination.
 *
 * Unlike chaos testing which picks randomly, this command systematically
 * tests EVERY combination to find specific issues.
 *
 * @example php artisan test:all-class-combinations --to-level=5
 * @example php artisan test:all-class-combinations --class=phb:fighter --to-level=20
 * @example php artisan test:all-class-combinations --cleanup
 */
class TestAllClassCombinationsCommand extends Command
{
    protected $signature = 'test:all-class-combinations
        {--class= : Test only a specific base class (e.g., phb:fighter)}
        {--subclass= : Test only a specific subclass (e.g., phb:fighter-battle-master)}
        {--to-level=1 : Target level for each character}
        {--seed= : Random seed for reproducibility}
        {--cleanup : Delete test characters after run}
        {--failures-only : Only show failures in output}
        {--export=json : Export results to JSON file}';

    protected $description = 'Test wizard flow + level-up for all class/subclass combinations systematically';

    private array $createdCharacters = [];

    public function handle(SystematicFlowRunner $runner): int
    {
        $seed = $this->option('seed') ? (int) $this->option('seed') : random_int(1, 999999);
        $targetLevel = (int) $this->option('to-level');
        $classFilter = $this->option('class');
        $subclassFilter = $this->option('subclass');

        $this->info('All Class Combinations Test');
        $this->info('===========================');
        $this->info("Seed: {$seed}");
        $this->info("Target Level: {$targetLevel}");
        $this->newLine();

        $results = [];

        // Get classes to test
        $baseClasses = $runner->getBaseClasses();

        // Filter if specified
        if ($classFilter) {
            $baseClasses = $baseClasses->filter(fn ($c) => $c->slug === $classFilter);
            if ($baseClasses->isEmpty()) {
                $this->error("Class not found: {$classFilter}");

                return Command::FAILURE;
            }
        }

        // Count total tests for progress
        $totalTests = 0;
        foreach ($baseClasses as $baseClass) {
            $subclasses = $runner->getSubclasses($baseClass);
            if ($subclassFilter) {
                $subclasses = $subclasses->filter(fn ($s) => $s->slug === $subclassFilter);
            }
            // Count: base class test (if subclass_level > 1) + each subclass
            if ($baseClass->subclass_level !== 1) {
                $totalTests++;
            }
            $totalTests += $subclasses->count();
        }

        $this->info("Testing {$totalTests} combinations...");
        $this->newLine();

        // Run tests with progress bar
        $completed = 0;
        $passed = 0;
        $failed = 0;

        foreach ($baseClasses as $classIndex => $baseClass) {
            $subclasses = $runner->getSubclasses($baseClass);

            if ($subclassFilter) {
                $subclasses = $subclasses->filter(fn ($s) => $s->slug === $subclassFilter);
            }

            // Test base class without subclass if subclass_level > 1
            if ($baseClass->subclass_level !== 1 && ! $subclassFilter) {
                $result = $runner->runFullTest(
                    $baseClass,
                    null,
                    $targetLevel,
                    $seed + ($classIndex * 100)
                );

                $results[] = $result;
                $this->displayResult($result);
                $this->trackCharacter($result);

                $completed++;
                $result['wizard_passed'] && $result['levelup_passed'] ? $passed++ : $failed++;
            }

            // Test each subclass
            foreach ($subclasses as $subIndex => $subclass) {
                $result = $runner->runFullTest(
                    $baseClass,
                    $subclass,
                    $targetLevel,
                    $seed + ($classIndex * 100) + $subIndex + 1
                );

                $results[] = $result;
                $this->displayResult($result);
                $this->trackCharacter($result);

                $completed++;
                $result['wizard_passed'] && $result['levelup_passed'] ? $passed++ : $failed++;
            }
        }

        $this->newLine();
        $this->displaySummary($passed, $failed, $targetLevel);

        // Export if requested
        if ($this->option('export') === 'json') {
            $this->exportResults($results, $seed, $targetLevel);
        }

        // Cleanup if requested
        if ($this->option('cleanup')) {
            $this->cleanupCharacters();
        }

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function displayResult(array $result): void
    {
        $isPass = $result['wizard_passed'] && $result['levelup_passed'];

        // Skip passes if failures-only mode
        if ($isPass && $this->option('failures-only')) {
            return;
        }

        $status = $isPass ? '<fg=green>PASS</>' : '<fg=red>FAIL</>';
        $classLabel = $result['subclass'] ?? $result['class'];
        $levelInfo = $result['final_level'] > 0 ? " (L{$result['final_level']})" : '';

        $this->line("[{$status}] {$classLabel}{$levelInfo}");

        // Show errors for failures
        if (! $isPass && ! empty($result['errors'])) {
            foreach ($result['errors'] as $error) {
                $this->line("    <fg=yellow>• {$error}</>");
            }
        }
    }

    private function displaySummary(int $passed, int $failed, int $targetLevel): void
    {
        $total = $passed + $failed;
        $passRate = $total > 0 ? round(($passed / $total) * 100, 1) : 0;

        $this->info('Summary');
        $this->info('-------');
        $this->line("Total: {$total}");
        $this->line("Passed: {$passed}");
        $this->line("Failed: {$failed}");
        $this->line("Pass Rate: {$passRate}%");
        $this->line("Target Level: {$targetLevel}");

        $this->newLine();
        if ($failed === 0) {
            $this->info('All combinations passed!');
        } else {
            $this->error("{$failed} combination(s) failed. Review errors above.");
        }
    }

    private function trackCharacter(array $result): void
    {
        if ($result['character_id']) {
            $this->createdCharacters[] = [
                'id' => $result['character_id'],
                'public_id' => $result['public_id'],
            ];
        }
    }

    private function cleanupCharacters(): void
    {
        if (empty($this->createdCharacters)) {
            return;
        }

        $this->newLine();
        $this->info('Cleaning up test characters...');

        $deleted = 0;
        foreach ($this->createdCharacters as $char) {
            try {
                Character::where('id', $char['id'])->delete();
                $deleted++;
            } catch (\Throwable $e) {
                $this->warn("Failed to delete character {$char['public_id']}: {$e->getMessage()}");
            }
        }

        $this->info("Deleted {$deleted} test characters.");
    }

    private function exportResults(array $results, int $seed, int $targetLevel): void
    {
        $report = [
            'generated_at' => now()->toIso8601String(),
            'seed' => $seed,
            'target_level' => $targetLevel,
            'summary' => [
                'total' => count($results),
                'passed' => count(array_filter($results, fn ($r) => $r['wizard_passed'] && $r['levelup_passed'])),
                'failed' => count(array_filter($results, fn ($r) => ! ($r['wizard_passed'] && $r['levelup_passed']))),
            ],
            'results' => $results,
        ];

        $filename = 'class-combination-results-'.now()->format('Y-m-d-His').'.json';
        $path = "reports/{$filename}";

        Storage::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->newLine();
        $this->info('Exported to: '.Storage::path($path));
    }
}
