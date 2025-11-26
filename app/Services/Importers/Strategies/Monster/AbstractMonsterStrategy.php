<?php

namespace App\Services\Importers\Strategies\Monster;

use App\Models\Monster;
use App\Services\Importers\Strategies\AbstractImportStrategy;

abstract class AbstractMonsterStrategy extends AbstractImportStrategy
{
    /**
     * Not used by MonsterImporter - uses enhanceTraits/enhanceActions instead.
     */
    public function enhance(array $data): array
    {
        return $data;
    }

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
     *
     * @param  Monster  $entity
     */
    public function afterCreate(\Illuminate\Database\Eloquent\Model $entity, array $data): void
    {
        // Override in strategies that need post-creation work
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
     * Check if monster data contains specific damage resistance.
     */
    protected function hasDamageResistance(array $monsterData, string $damageType): bool
    {
        return str_contains(strtolower($monsterData['damage_resistances'] ?? ''), strtolower($damageType));
    }

    /**
     * Check if monster data contains specific damage immunity.
     */
    protected function hasDamageImmunity(array $monsterData, string $damageType): bool
    {
        return str_contains(strtolower($monsterData['damage_immunities'] ?? ''), strtolower($damageType));
    }

    /**
     * Check if monster data contains specific condition immunity.
     */
    protected function hasConditionImmunity(array $monsterData, string $condition): bool
    {
        $immunities = strtolower($monsterData['condition_immunities'] ?? '');

        return str_contains($immunities, strtolower($condition));
    }

    /**
     * Check if any trait contains a specific keyword (case-insensitive).
     */
    protected function hasTraitContaining(array $traits, string $keyword): bool
    {
        foreach ($traits as $trait) {
            $name = strtolower($trait['name'] ?? '');
            $description = strtolower($trait['description'] ?? '');
            $keyword = strtolower($keyword);

            if (str_contains($name, $keyword) || str_contains($description, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
