<?php

namespace App\Services\Importers\Strategies;

use Illuminate\Database\Eloquent\Model;

/**
 * Base class for all import strategies.
 *
 * Provides common functionality for warnings, metrics, and lifecycle hooks.
 * Concrete strategies implement appliesTo() and enhance().
 */
abstract class AbstractImportStrategy
{
    protected array $warnings = [];

    protected array $metrics = [];

    /**
     * Determine if this strategy applies to the given data.
     */
    abstract public function appliesTo(array $data): bool;

    /**
     * Enhance entity data with strategy-specific logic.
     */
    abstract public function enhance(array $data): array;

    /**
     * Post-creation hook for additional relationship syncing.
     */
    public function afterCreate(Model $entity, array $data): void
    {
        // Default: no-op
    }

    /**
     * Extract metadata for logging and statistics.
     */
    public function extractMetadata(array $data): array
    {
        return [
            'warnings' => $this->warnings,
            'metrics' => $this->metrics,
        ];
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
}
