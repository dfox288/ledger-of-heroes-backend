<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\LevelUpFlowTesting\LevelUpFlowExecutor;
use App\Services\LevelUpFlowTesting\LevelUpReportGenerator;
use App\Services\WizardFlowTesting\CharacterRandomizer;
use App\Services\WizardFlowTesting\FlowExecutor;
use App\Services\WizardFlowTesting\FlowGenerator;
use Illuminate\Console\Command;

/**
 * Test character level-up flow with chaos testing.
 *
 * Creates characters via wizard flow and levels them from 1→target,
 * validating each level-up step and finding bugs.
 *
 * @example php artisan test:level-up-flow --count=5 --target-level=10
 * @example php artisan test:level-up-flow --chaos --count=10
 * @example php artisan test:level-up-flow --force-class=phb:fighter --target-level=20
 */
class TestLevelUpFlowCommand extends Command
{
    protected $signature = 'test:level-up-flow
        {--count=1 : Number of iterations to run}
        {--target-level=20 : Target level to reach}
        {--chaos : Enable random multiclassing and choices}
        {--seed= : Random seed for reproducibility}
        {--force-class= : Force specific starting class (e.g., phb:fighter)}
        {--character= : Use existing character ID instead of creating new}
        {--cleanup : Delete test characters after run}
        {--verbose-steps : Show each step as it executes}
        {--list-reports : List previous test reports}
        {--show-report= : Show a specific report by run_id}';

    protected $description = 'Test character level-up flow with chaos/stress testing';

    public function handle(): int
    {
        // Handle report listing/viewing
        if ($this->option('list-reports')) {
            return $this->listReports();
        }

        if ($this->option('show-report')) {
            return $this->showReport($this->option('show-report'));
        }

        $seed = $this->option('seed') ? (int) $this->option('seed') : random_int(1, 999999);
        $count = (int) $this->option('count');
        $targetLevel = (int) $this->option('target-level');
        $chaosMode = (bool) $this->option('chaos');
        $forceClass = $this->option('force-class');

        $this->info('Level-Up Flow Testing');
        $this->info('=====================');
        $this->info("Seed: {$seed}");
        $this->info("Iterations: {$count}");
        $this->info("Target Level: {$targetLevel}");
        $this->info('Mode: '.($chaosMode ? 'Chaos' : 'Linear'));
        if ($forceClass) {
            $this->info("Forced Class: {$forceClass}");
        }
        $this->newLine();

        $levelUpExecutor = new LevelUpFlowExecutor;
        $reporter = new LevelUpReportGenerator;
        $results = [];

        $options = $this->collectOptions();

        // Progress bar for multiple iterations
        $this->withProgressBar(range(1, $count), function ($i) use (
            &$results,
            $levelUpExecutor,
            $seed,
            $targetLevel,
            $chaosMode,
            $forceClass
        ) {
            $randomizer = new CharacterRandomizer($seed + $i - 1);

            // Create a complete character first
            $characterId = $this->option('character')
                ? (int) $this->option('character')
                : $this->createTestCharacter($randomizer, $forceClass);

            if ($characterId === null) {
                $this->newLine();
                $this->error("Failed to create character for iteration {$i}");

                return;
            }

            // Execute level-up flow
            $result = $levelUpExecutor->execute(
                characterId: $characterId,
                targetLevel: $targetLevel,
                randomizer: $randomizer,
                iteration: $i,
                chaosMode: $chaosMode
            );

            if ($this->option('verbose-steps')) {
                $this->newLine();
                $this->line($result->getSummary());
            }

            $results[] = $result;
        });

        $this->newLine(2);

        // Generate and save report
        $report = $reporter->generate($results, $seed, $options);
        $path = $reporter->save($report);

        // Display summary
        $this->displaySummary($report, $reporter);

        $this->newLine();
        $this->info("Report saved to: {$path}");

        // Cleanup if requested
        if ($this->option('cleanup')) {
            $this->cleanupCharacters($report);
        }

        return $report['summary']['failed'] > 0 || $report['summary']['errors'] > 0
            ? Command::FAILURE
            : Command::SUCCESS;
    }

    /**
     * Create a complete test character using wizard flow.
     */
    private function createTestCharacter(CharacterRandomizer $randomizer, ?string $forceClass = null): ?int
    {
        $wizardGenerator = new FlowGenerator;
        $wizardExecutor = new FlowExecutor;

        // Generate linear wizard flow
        $flow = $wizardGenerator->linear();

        // Apply --force-class option
        if ($forceClass) {
            foreach ($flow as &$step) {
                if ($step['action'] === 'set_class') {
                    $step['force_class'] = $forceClass;
                    break;
                }
            }
        }

        $wizardResult = $wizardExecutor->execute($flow, $randomizer);

        if (! $wizardResult->isPassed()) {
            if ($this->option('verbose-steps')) {
                $this->warn('Wizard flow failed: '.$wizardResult->getSummary());
            }

            return null;
        }

        return $wizardResult->getCharacterId();
    }

    private function collectOptions(): array
    {
        return [
            'target_level' => (int) $this->option('target-level'),
            'chaos' => $this->option('chaos'),
            'force_class' => $this->option('force-class'),
            'character' => $this->option('character'),
        ];
    }

    private function displaySummary(array $report, LevelUpReportGenerator $reporter): void
    {
        $lines = $reporter->consoleSummary($report);
        foreach ($lines as $line) {
            $this->line($line);
        }

        // Color-coded status
        $this->newLine();
        if ($report['summary']['failed'] === 0 && $report['summary']['errors'] === 0) {
            $this->info('All tests passed!');
        } else {
            $this->error("Tests failed: {$report['summary']['failed']} failures, {$report['summary']['errors']} errors");
        }
    }

    private function cleanupCharacters(array $report): void
    {
        $this->info('Cleaning up test characters...');

        $deleted = 0;
        foreach ($report['summary']['characters_used'] as $char) {
            try {
                \App\Models\Character::where('id', $char['id'])->delete();
                $deleted++;
            } catch (\Throwable $e) {
                $this->warn("Failed to delete character {$char['public_id']}: {$e->getMessage()}");
            }
        }

        $this->info("Deleted {$deleted} test characters.");
    }

    private function listReports(): int
    {
        $reporter = new LevelUpReportGenerator;
        $reports = $reporter->listReports();

        if (empty($reports)) {
            $this->info('No reports found.');

            return Command::SUCCESS;
        }

        $this->table(
            ['Run ID', 'Timestamp', 'Total', 'Passed', 'Failed', 'Pass Rate', 'Max Level'],
            array_map(fn ($r) => [
                substr($r['run_id'], 0, 8).'...',
                $r['timestamp'] ? substr($r['timestamp'], 0, 19) : 'N/A',
                $r['total'],
                $r['passed'],
                $r['failed'],
                $r['pass_rate'].'%',
                $r['max_level'],
            ], $reports)
        );

        return Command::SUCCESS;
    }

    private function showReport(string $runId): int
    {
        $reporter = new LevelUpReportGenerator;
        $report = $reporter->load($runId);

        if (! $report) {
            $this->error("Report not found: {$runId}");

            return Command::FAILURE;
        }

        $lines = $reporter->consoleSummary($report);
        foreach ($lines as $line) {
            $this->line($line);
        }

        // Show detailed failures if any
        if (! empty($report['results'])) {
            $this->newLine();
            $this->info('Detailed Results:');

            foreach ($report['results'] as $result) {
                if ($result['status'] !== 'PASS') {
                    $this->newLine();
                    $this->line("[{$result['status']}] Character: {$result['public_id']} (level {$result['final_level']})");

                    if (! empty($result['failures'])) {
                        foreach ($result['failures'] as $failure) {
                            $this->error("  Level {$failure['level']}: {$failure['class_slug']}");
                            foreach ($failure['errors'] as $error) {
                                $this->line("    - {$error}");
                            }
                        }
                    }

                    if (! empty($result['error'])) {
                        $this->error("  Error at level {$result['error']['at_level']}: {$result['error']['message']}");
                    }
                }
            }
        }

        return Command::SUCCESS;
    }
}
