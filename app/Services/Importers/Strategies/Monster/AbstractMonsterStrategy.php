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

    /**
     * Apply conditional tags based on trait/immunity/resistance/condition checks.
     *
     * Consolidates the common pattern of accumulating tags based on various
     * monster attributes. Each condition key uses a prefix to indicate the
     * check type:
     * - 'trait:keyword' - checks if any trait contains the keyword
     * - 'immunity:type' - checks for damage immunity
     * - 'resistance:type' - checks for damage resistance
     * - 'condition:name' - checks for condition immunity
     *
     * Values can be either:
     * - A string tag name (metric defaults to '{tag}_count')
     * - An array with 'tag' and 'metric' keys for custom metric names
     *
     * @param  string  $primaryTag  The base tag for this monster type (e.g., 'beast', 'fiend')
     * @param  array<string, string|array{tag: string, metric: string}>  $conditions  Condition checks mapped to tags
     * @param  array  $traits  The monster's parsed traits
     * @param  array  $monsterData  The full monster data array
     */
    protected function applyConditionalTags(
        string $primaryTag,
        array $conditions,
        array $traits,
        array $monsterData
    ): void {
        $tags = [$primaryTag];

        foreach ($conditions as $check => $tagInfo) {
            $tag = is_array($tagInfo) ? $tagInfo['tag'] : $tagInfo;
            $metric = is_array($tagInfo) ? $tagInfo['metric'] : "{$tag}_count";

            $matches = match (true) {
                str_starts_with($check, 'trait:') => $this->hasTraitContaining($traits, substr($check, 6)),
                str_starts_with($check, 'immunity:') => $this->hasDamageImmunity($monsterData, substr($check, 9)),
                str_starts_with($check, 'resistance:') => $this->hasDamageResistance($monsterData, substr($check, 11)),
                str_starts_with($check, 'condition:') => $this->hasConditionImmunity($monsterData, substr($check, 10)),
                default => false,
            };

            if ($matches) {
                // Always increment the metric
                $this->incrementMetric($metric);
                // Only add tag if not already present (deduplication)
                if (! in_array($tag, $tags, true)) {
                    $tags[] = $tag;
                }
            }
        }

        $this->setMetric('tags_applied', $tags);
        $this->incrementMetric("{$primaryTag}s_enhanced");
    }
}
