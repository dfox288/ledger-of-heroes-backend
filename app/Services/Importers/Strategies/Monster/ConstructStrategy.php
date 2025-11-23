<?php

namespace App\Services\Importers\Strategies\Monster;

class ConstructStrategy extends AbstractMonsterStrategy
{
    /**
     * Determine if this strategy applies to constructs.
     */
    public function appliesTo(array $monsterData): bool
    {
        $type = strtolower($monsterData['type'] ?? '');

        return str_contains($type, 'construct');
    }

    /**
     * Enhance traits with construct-specific detection (immunities, constructed nature).
     */
    public function enhanceTraits(array $traits, array $monsterData): array
    {
        $tags = ['construct'];

        // Most constructs are poison immune
        if ($this->hasDamageImmunity($monsterData, 'poison')) {
            $tags[] = 'poison_immune';
        }

        // Check for condition immunities (common in constructs)
        $conditionImmune = false;
        foreach (['charm', 'exhaustion', 'frightened', 'paralyzed', 'petrified'] as $condition) {
            if ($this->hasConditionImmunity($monsterData, $condition)) {
                $conditionImmune = true;
                break;
            }
        }

        if ($conditionImmune) {
            $tags[] = 'condition_immune';
            $this->incrementMetric('condition_immune_count');
        }

        // Detect "Constructed Nature" trait
        if ($this->hasTraitContaining($traits, 'constructed nature')
            || $this->hasTraitContaining($traits, "doesn't require air")) {
            $tags[] = 'constructed_nature';
            $this->incrementMetric('constructed_nature_count');
        }

        // Store tags and increment enhancement counter
        $this->setMetric('tags_applied', $tags);
        $this->incrementMetric('constructs_enhanced');

        return $traits;
    }
}
