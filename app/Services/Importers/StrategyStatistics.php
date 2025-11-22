<?php

namespace App\Services\Importers;

use Illuminate\Support\Facades\File;

/**
 * Collects and formats statistics from item parser strategies.
 *
 * Reads the import-strategy log file and aggregates metrics by strategy.
 */
class StrategyStatistics
{
    /**
     * Get statistics from today's import-strategy log.
     *
     * @return array<string, array{items_enhanced: int, warnings: int, metrics: array}>
     */
    public function getStatistics(): array
    {
        $logPath = storage_path('logs/import-strategy-'.date('Y-m-d').'.log');

        if (! File::exists($logPath)) {
            return [];
        }

        $stats = [];
        $lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            // Parse JSON log entry - skip lines without our marker
            if (! str_contains($line, 'Strategy applied:')) {
                continue;
            }

            // Extract JSON portion - it comes after "INFO: Strategy applied: StrategyName "
            // Format: [timestamp] env.LEVEL: Strategy applied: Name {"json":"here"}
            if (! preg_match('/\{.*\}/', $line, $matches)) {
                continue;
            }

            $data = json_decode($matches[0], true);
            if (! $data || ! isset($data['strategy'])) {
                continue;
            }

            $strategyName = $data['strategy'];

            // Initialize strategy stats if not exists
            if (! isset($stats[$strategyName])) {
                $stats[$strategyName] = [
                    'items_enhanced' => 0,
                    'warnings' => 0,
                    'metrics' => [],
                ];
            }

            // Increment counters
            $stats[$strategyName]['items_enhanced']++;
            $stats[$strategyName]['warnings'] += count($data['warnings'] ?? []);

            // Aggregate metrics (sum numeric values)
            foreach ($data['metrics'] ?? [] as $key => $value) {
                if (is_numeric($value)) {
                    if (! isset($stats[$strategyName]['metrics'][$key])) {
                        $stats[$strategyName]['metrics'][$key] = 0;
                    }
                    $stats[$strategyName]['metrics'][$key] += $value;
                }
            }
        }

        return $stats;
    }

    /**
     * Format statistics as a table for console display.
     *
     * @param  array  $stats  Statistics from getStatistics()
     * @return array Array of table rows [strategy, items, warnings, key_metrics]
     */
    public function formatForDisplay(array $stats): array
    {
        $rows = [];

        foreach ($stats as $strategy => $data) {
            // Extract key metrics (limit to 2-3 most relevant)
            $keyMetrics = $this->extractKeyMetrics($data['metrics']);

            $rows[] = [
                'strategy' => $strategy,
                'items' => $data['items_enhanced'],
                'warnings' => $data['warnings'],
                'metrics' => $keyMetrics,
            ];
        }

        return $rows;
    }

    /**
     * Extract 2-3 most relevant metrics for display.
     *
     * @param  array  $metrics  All metrics for a strategy
     * @return string Formatted metric string
     */
    private function extractKeyMetrics(array $metrics): string
    {
        if (empty($metrics)) {
            return '-';
        }

        // Prioritize certain metrics
        $priority = [
            'spell_references_found',
            'spells_matched',
            'spell_level',
            'effect_category',
            'sentient_items',
            'spell_scrolls',
            'protection_scrolls',
        ];

        $selected = [];
        foreach ($priority as $key) {
            if (isset($metrics[$key]) && count($selected) < 3) {
                $value = $metrics[$key];
                $label = str_replace('_', ' ', $key);
                $selected[] = "{$label}: {$value}";
            }
        }

        // If no priority metrics, take first 3
        if (empty($selected)) {
            $count = 0;
            foreach ($metrics as $key => $value) {
                if ($count >= 3) {
                    break;
                }
                $label = str_replace('_', ' ', $key);
                $selected[] = "{$label}: {$value}";
                $count++;
            }
        }

        return implode(', ', $selected);
    }

    /**
     * Clear the import-strategy log file.
     *
     * Should be called before starting a new import to reset statistics.
     */
    public function clearLog(): void
    {
        $logPath = storage_path('logs/import-strategy-'.date('Y-m-d').'.log');

        if (File::exists($logPath)) {
            File::put($logPath, '');
        }
    }
}
