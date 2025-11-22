<?php

namespace App\Services\Importers\Strategies\Monster;

use App\Models\Monster;

abstract class AbstractMonsterStrategy
{
    /**
     * Determine if this strategy applies to the given monster data
     */
    abstract public function appliesTo(array $monsterData): bool;

    /**
     * Apply type-specific enhancements to parsed traits
     */
    public function enhanceTraits(array $traits, array $monsterData): array
    {
        return $traits; // Default: no enhancement
    }

    /**
     * Apply type-specific action parsing (multiattack, recharge, etc.)
     */
    public function enhanceActions(array $actions, array $monsterData): array
    {
        return $actions; // Default: no enhancement
    }

    /**
     * Parse legendary actions with cost detection
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
     * (e.g., SpellcasterStrategy syncs spells)
     */
    public function afterCreate(Monster $monster, array $monsterData): void
    {
        // Override in strategies that need post-creation work
    }

    /**
     * Extract metadata for logging and statistics
     */
    public function extractMetadata(array $monsterData): array
    {
        return []; // Strategy-specific metrics
    }

    /**
     * Extract action cost from legendary action name
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
}
