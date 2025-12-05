<?php

namespace App\Services\Concerns;

/**
 * Provides warning and metric tracking for import/parse operations.
 *
 * Used by AbstractImportStrategy and AbstractItemStrategy.
 */
trait TracksMetricsAndWarnings
{
    protected array $warnings = [];

    protected array $metrics = [];

    protected function addWarning(string $message): void
    {
        $this->warnings[] = $message;
    }

    protected function incrementMetric(string $key, int $amount = 1): void
    {
        if (! isset($this->metrics[$key])) {
            $this->metrics[$key] = 0;
        }
        $this->metrics[$key] += $amount;
    }

    protected function setMetric(string $key, mixed $value): void
    {
        $this->metrics[$key] = $value;
    }

    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function getMetrics(): array
    {
        return $this->metrics;
    }

    public function reset(): void
    {
        $this->warnings = [];
        $this->metrics = [];
    }
}
