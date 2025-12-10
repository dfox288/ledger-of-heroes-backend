<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\WizardFlowTesting\CharacterRandomizer;
use App\Services\WizardFlowTesting\FlowExecutor;
use App\Services\WizardFlowTesting\FlowGenerator;
use App\Services\WizardFlowTesting\ReportGenerator;
use Illuminate\Console\Command;

/**
 * Test character wizard flow with switch/backtrack chaos testing.
 *
 * This command simulates the frontend character wizard, with emphasis on
 * SWITCHING between options mid-flow to find bugs in cascade/reset logic.
 *
 * @example php artisan test:wizard-flow --count=10 --chaos
 * @example php artisan test:wizard-flow --switches=race,background,race
 * @example php artisan test:wizard-flow --seed=12345 --chaos
 */
class TestWizardFlowCommand extends Command
{
    protected $signature = 'test:wizard-flow
        {--count=1 : Number of iterations to run}
        {--chaos : Enable random switches at random points}
        {--switches= : Specific switch sequence (comma-separated: race,background,class)}
        {--seed= : Random seed for reproducibility}
        {--all-races : Test every race with chaos flow}
        {--equipment-modes : Test equipment mode switching}
        {--class-types= : Test class type switching (spellcaster,martial)}
        {--force-class= : Force a specific class (e.g., phb:cleric)}
        {--min-switches=1 : Minimum switches in chaos mode}
        {--max-switches=3 : Maximum switches in chaos mode}
        {--cleanup : Delete test characters after run (default: keep)}
        {--verbose-steps : Show each step as it executes}
        {--list-reports : List previous test reports}
        {--show-report= : Show a specific report by run_id}';

    protected $description = 'Test character wizard flow with switch/backtrack chaos testing';

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

        $this->info('Wizard Flow Chaos Testing');
        $this->info('========================');
        $this->info("Seed: {$seed}");
        $this->info("Iterations: {$count}");
        $this->newLine();

        $generator = new FlowGenerator;
        $executor = new FlowExecutor;
        $reporter = new ReportGenerator;

        $results = [];
        $options = $this->collectOptions();

        // Determine flow type
        $flowType = $this->determineFlowType();
        $this->info("Flow type: {$flowType}");
        $this->newLine();

        // Progress bar for multiple iterations
        $this->withProgressBar(range(1, $count), function ($i) use (
            &$results,
            $generator,
            $executor,
            $seed,
            $flowType
        ) {
            $randomizer = new CharacterRandomizer($seed + $i - 1);

            $flow = $this->generateFlow($generator, $randomizer, $flowType);
            $result = $executor->execute($flow, $randomizer, $i);

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

    private function determineFlowType(): string
    {
        if ($this->option('all-races')) {
            return 'all_races';
        }

        if ($this->option('equipment-modes')) {
            return 'equipment_chaos';
        }

        if ($this->option('class-types')) {
            return 'class_type_switch';
        }

        if ($this->option('switches')) {
            return 'parameterized';
        }

        if ($this->option('chaos')) {
            return 'chaos';
        }

        return 'linear';
    }

    private function generateFlow(FlowGenerator $generator, CharacterRandomizer $randomizer, string $flowType): array
    {
        $flow = match ($flowType) {
            'chaos' => $generator->chaos(
                $randomizer,
                (int) $this->option('min-switches'),
                (int) $this->option('max-switches')
            ),
            'parameterized' => $generator->withSwitches(
                explode(',', $this->option('switches'))
            ),
            'equipment_chaos' => $generator->equipmentModeChaos($randomizer),
            'class_type_switch' => $this->generateClassTypeSwitchFlow($generator),
            'linear' => $generator->linear(),
            default => $generator->linear(),
        };

        // Apply --force-class option to set_class step
        if ($forceClass = $this->option('force-class')) {
            foreach ($flow as &$step) {
                if ($step['action'] === 'set_class') {
                    $step['force_class'] = $forceClass;
                    break;
                }
            }
        }

        return $flow;
    }

    private function generateClassTypeSwitchFlow(FlowGenerator $generator): array
    {
        $types = explode(',', $this->option('class-types') ?? 'spellcaster,martial');

        return $generator->classTypeSwitchFlow($types[0] ?? 'spellcaster', $types[1] ?? 'martial');
    }

    private function collectOptions(): array
    {
        return [
            'chaos' => $this->option('chaos'),
            'switches' => $this->option('switches'),
            'all_races' => $this->option('all-races'),
            'equipment_modes' => $this->option('equipment-modes'),
            'class_types' => $this->option('class-types'),
            'force_class' => $this->option('force-class'),
            'min_switches' => $this->option('min-switches'),
            'max_switches' => $this->option('max-switches'),
        ];
    }

    private function displaySummary(array $report, ReportGenerator $reporter): void
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
        foreach ($report['summary']['characters_created'] as $char) {
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
        $reporter = new ReportGenerator;
        $reports = $reporter->listReports();

        if (empty($reports)) {
            $this->info('No reports found.');

            return Command::SUCCESS;
        }

        $this->table(
            ['Run ID', 'Timestamp', 'Total', 'Passed', 'Failed', 'Pass Rate'],
            array_map(fn ($r) => [
                substr($r['run_id'], 0, 8).'...',
                $r['timestamp'] ? substr($r['timestamp'], 0, 19) : 'N/A',
                $r['total'],
                $r['passed'],
                $r['failed'],
                $r['pass_rate'].'%',
            ], $reports)
        );

        return Command::SUCCESS;
    }

    private function showReport(string $runId): int
    {
        $reporter = new ReportGenerator;
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
                    $this->line("[{$result['status']}] Character: {$result['public_id']}");

                    if (! empty($result['failures'])) {
                        foreach ($result['failures'] as $failure) {
                            $this->error("  Step: {$failure['step']}");
                            foreach ($failure['errors'] as $error) {
                                $this->line("    - {$error}");
                            }

                            if (! empty($failure['diff'])) {
                                $this->line('  Diff:');
                                foreach ($failure['diff'] as $key => $diff) {
                                    $before = is_array($diff['before']) ? json_encode($diff['before']) : $diff['before'];
                                    $after = is_array($diff['after']) ? json_encode($diff['after']) : $diff['after'];
                                    $this->line("    {$key}: {$before} => {$after}");
                                }
                            }
                        }
                    }
                }
            }
        }

        return Command::SUCCESS;
    }
}
