<?php

namespace App\Services\Parsers\Strategies;

use App\Services\Parsers\Concerns\LookupsGameEntities;

abstract class AbstractItemStrategy implements ItemTypeStrategy
{
    use LookupsGameEntities;

    /**
     * Warnings generated during parsing.
     */
    protected array $warnings = [];

    /**
     * Metrics tracked during parsing.
     */
    protected array $metrics = [];

    /**
     * Default implementation: no modifier enhancements.
     */
    public function enhanceModifiers(array $modifiers, array $baseData, \SimpleXMLElement $xml): array
    {
        return $modifiers;
    }

    /**
     * Default implementation: no ability enhancements.
     */
    public function enhanceAbilities(array $abilities, array $baseData, \SimpleXMLElement $xml): array
    {
        return $abilities;
    }

    /**
     * Default implementation: no relationship enhancements.
     */
    public function enhanceRelationships(array $baseData, \SimpleXMLElement $xml): array
    {
        return [];
    }

    /**
     * Extract metadata collected during parsing.
     */
    public function extractMetadata(): array
    {
        return [
            'warnings' => $this->warnings,
            'metrics' => $this->metrics,
        ];
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
    protected function incrementMetric(string $key, int $amount = 1): void
    {
        if (! isset($this->metrics[$key])) {
            $this->metrics[$key] = 0;
        }

        $this->metrics[$key] += $amount;
    }

    /**
     * Set a metric value.
     */
    protected function setMetric(string $key, mixed $value): void
    {
        $this->metrics[$key] = $value;
    }

    /**
     * Reset warnings and metrics (called before processing each item).
     */
    public function reset(): void
    {
        $this->warnings = [];
        $this->metrics = [];
    }
}
