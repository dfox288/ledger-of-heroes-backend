<?php

declare(strict_types=1);

namespace App\Services\LevelUpFlowTesting;

use Illuminate\Support\Str;

/**
 * Generates JSON reports from level-up flow test results.
 */
class LevelUpReportGenerator
{
    /**
     * Generate a full report from test results.
     *
     * @param  LevelUpFlowResult[]  $results
     */
    public function generate(array $results, int $seed, array $options = []): array
    {
        $report = [
            'run_id' => Str::uuid()->toString(),
            'timestamp' => now()->toIso8601String(),
            'seed' => $seed,
            'options' => $options,
            'iterations' => count($results),
            'results' => [],
            'summary' => [
                'total' => count($results),
                'passed' => 0,
                'failed' => 0,
                'errors' => 0,
                'failure_patterns' => [],
                'characters_used' => [],
                'level_stats' => [
                    'max_reached' => 0,
                    'avg_reached' => 0,
                    'total_levels_gained' => 0,
                ],
            ],
        ];

        $levelsReached = [];
        $totalLevelsGained = 0;

        foreach ($results as $result) {
            $report['results'][] = $result->toArray();

            // Track used characters
            $report['summary']['characters_used'][] = [
                'id' => $result->getCharacterId(),
                'public_id' => $result->getPublicId(),
                'status' => $result->getStatus(),
                'final_level' => $result->getFinalLevel(),
            ];

            // Track level stats
            $levelsReached[] = $result->getFinalLevel();
            $totalLevelsGained += count($result->getSteps());

            // Update counts
            if ($result->isPassed()) {
                $report['summary']['passed']++;
            } elseif ($result->hasError()) {
                $report['summary']['errors']++;
            } else {
                $report['summary']['failed']++;

                // Track failure patterns
                foreach ($result->getFailures() as $failure) {
                    $pattern = $failure->pattern ?? 'unknown';
                    $report['summary']['failure_patterns'][$pattern] =
                        ($report['summary']['failure_patterns'][$pattern] ?? 0) + 1;
                }
            }
        }

        // Calculate level stats
        if (! empty($levelsReached)) {
            $report['summary']['level_stats']['max_reached'] = max($levelsReached);
            $report['summary']['level_stats']['avg_reached'] = round(array_sum($levelsReached) / count($levelsReached), 1);
            $report['summary']['level_stats']['total_levels_gained'] = $totalLevelsGained;
        }

        // Calculate pass rate
        $report['summary']['pass_rate'] = $report['summary']['total'] > 0
            ? round(($report['summary']['passed'] / $report['summary']['total']) * 100, 1)
            : 0;

        return $report;
    }

    /**
     * Save report to storage.
     */
    public function save(array $report): string
    {
        $directory = storage_path('level-up-flow-reports');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = $report['run_id'].'.json';
        $path = "{$directory}/{$filename}";

        file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    /**
     * Generate a console-friendly summary.
     */
    public function consoleSummary(array $report): array
    {
        $lines = [];

        $lines[] = '';
        $lines[] = "Run ID: {$report['run_id']}";
        $lines[] = "Seed: {$report['seed']}";
        $lines[] = '';
        $lines[] = '--- Results ---';
        $lines[] = "Total:   {$report['summary']['total']}";
        $lines[] = "Passed:  {$report['summary']['passed']} ({$report['summary']['pass_rate']}%)";
        $lines[] = "Failed:  {$report['summary']['failed']}";
        $lines[] = "Errors:  {$report['summary']['errors']}";

        if (! empty($report['summary']['level_stats'])) {
            $lines[] = '';
            $lines[] = '--- Level Stats ---';
            $lines[] = "Max level reached:    {$report['summary']['level_stats']['max_reached']}";
            $lines[] = "Avg level reached:    {$report['summary']['level_stats']['avg_reached']}";
            $lines[] = "Total levels gained:  {$report['summary']['level_stats']['total_levels_gained']}";
        }

        if (! empty($report['summary']['failure_patterns'])) {
            $lines[] = '';
            $lines[] = '--- Failure Patterns ---';
            foreach ($report['summary']['failure_patterns'] as $pattern => $count) {
                $lines[] = "  {$pattern}: {$count}";
            }
        }

        // Show individual failures
        $failedResults = array_filter($report['results'], fn ($r) => $r['status'] !== 'PASS');
        if (! empty($failedResults)) {
            $lines[] = '';
            $lines[] = '--- Failed Runs ---';
            foreach ($failedResults as $result) {
                $lines[] = "  [{$result['status']}] {$result['public_id']} (level {$result['final_level']})";
                if (! empty($result['failures'])) {
                    foreach ($result['failures'] as $failure) {
                        $errors = is_array($failure['errors']) ? implode('; ', $failure['errors']) : $failure['errors'];
                        $lines[] = "    - Level {$failure['level']}: {$errors}";
                    }
                }
                if (! empty($result['error'])) {
                    $lines[] = "    - Error at level {$result['error']['at_level']}: {$result['error']['message']}";
                }
            }
        }

        return $lines;
    }

    /**
     * Load a previously saved report.
     */
    public function load(string $runId): ?array
    {
        $path = storage_path("level-up-flow-reports/{$runId}.json");

        if (! file_exists($path)) {
            return null;
        }

        return json_decode(file_get_contents($path), true);
    }

    /**
     * List all saved reports.
     */
    public function listReports(): array
    {
        $directory = storage_path('level-up-flow-reports');

        if (! is_dir($directory)) {
            return [];
        }

        $files = glob("{$directory}/*.json");
        $reports = [];

        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            $reports[] = [
                'run_id' => $data['run_id'] ?? basename($file, '.json'),
                'timestamp' => $data['timestamp'] ?? null,
                'total' => $data['summary']['total'] ?? 0,
                'passed' => $data['summary']['passed'] ?? 0,
                'failed' => $data['summary']['failed'] ?? 0,
                'pass_rate' => $data['summary']['pass_rate'] ?? 0,
                'max_level' => $data['summary']['level_stats']['max_reached'] ?? 0,
            ];
        }

        // Sort by timestamp descending
        usort($reports, fn ($a, $b) => ($b['timestamp'] ?? '') <=> ($a['timestamp'] ?? ''));

        return $reports;
    }
}
