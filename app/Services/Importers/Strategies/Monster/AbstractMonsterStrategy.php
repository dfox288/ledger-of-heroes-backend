<?php

namespace App\Services\Importers\Strategies\Monster;

use App\Models\Monster;

abstract class AbstractMonsterStrategy
{
    /**
     * Warnings generated during strategy application.
     */
    protected array $warnings = [];

    /**
     * Metrics tracked during strategy application.
     */
    protected array $metrics = [];

    /**
     * Determine if this strategy applies to the given monster data.
     */
    abstract public function appliesTo(array $monsterData): bool;

    /**
     * Apply type-specific enhancements to parsed traits.
     */
    public function enhanceTraits(array $traits, array $monsterData): array
    {
        return $traits; // Default: no enhancement
    }

    /**
     * Apply type-specific action parsing (multiattack, recharge, etc.).
     */
    public function enhanceActions(array $actions, array $monsterData): array
    {
        return $actions; // Default: no enhancement
    }

    /**
     * Parse legendary actions with cost detection.
     */
    public function enhanceLegendaryActions(array $legendary, array $monsterData): array
    {
        foreach ($legendary as &$action) {
            // Extract cost from name: "Psychic Drain (Costs 2 Actions)" → 2
            $action['action_cost'] = $this->extractActionCost($action['name']);

            // Detect lair actions via category attribute
            $action['is_lair_action'] = ($action['category'] ?? null) === 'lair';
        }

        return $legendary;
    }

    /**
     * Post-creation hook for additional relationship syncing
     * (e.g., SpellcasterStrategy syncs spells).
     */
    public function afterCreate(Monster $monster, array $monsterData): void
    {
        // Override in strategies that need post-creation work
    }

    /**
     * Extract metadata for logging and statistics.
     */
    public function extractMetadata(array $monsterData): array
    {
        return [
            'warnings' => $this->warnings,
            'metrics' => $this->metrics,
        ];
    }

    /**
     * Extract action cost from legendary action name.
     * "Wing Attack (Costs 2 Actions)" → 2
     * "Detect" → 1 (default)
     */
    protected function extractActionCost(string $name): int
    {
        if (preg_match('/\(Costs? (\d+) Actions?\)/i', $name, $matches)) {
            return (int) $matches[1];
        }

        return 1; // Default cost
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
     * Reset warnings and metrics (called before processing each monster).
     */
    public function reset(): void
    {
        $this->warnings = [];
        $this->metrics = [];
    }
}
