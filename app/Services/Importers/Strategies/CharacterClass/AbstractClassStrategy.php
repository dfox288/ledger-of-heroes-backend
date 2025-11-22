<?php

namespace App\Services\Importers\Strategies\CharacterClass;

abstract class AbstractClassStrategy
{
    protected array $warnings = [];

    protected array $metrics = [];

    /**
     * Determine if this strategy applies to the given class data.
     */
    abstract public function appliesTo(array $data): bool;

    /**
     * Enhance class data with strategy-specific logic.
     */
    abstract public function enhance(array $data): array;

    /**
     * Get warnings generated during enhancement.
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * Get metrics tracked during enhancement.
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }

    /**
     * Reset warnings and metrics for next entity.
     */
    public function reset(): void
    {
        $this->warnings = [];
        $this->metrics = [];
    }

    /**
     * Add a warning message.
     */
    protected function addWarning(string $message): void
    {
        $this->warnings[] = $message;
    }

    /**
     * Increment a metric counter.
     */
    protected function incrementMetric(string $key): void
    {
        $this->metrics[$key] = ($this->metrics[$key] ?? 0) + 1;
    }
}
