<?php

namespace App\Services\Importers\Strategies\Monster;

class FiendStrategy extends AbstractMonsterStrategy
{
    /**
     * Determine if this strategy applies to fiends (devils, demons, yugoloths).
     */
    public function appliesTo(array $monsterData): bool
    {
        $type = strtolower($monsterData['type'] ?? '');

        return str_contains($type, 'fiend')
            || str_contains($type, 'devil')
            || str_contains($type, 'demon')
            || str_contains($type, 'yugoloth');
    }

    /**
     * Enhance traits with fiend-specific detection.
     */
    public function enhanceTraits(array $traits, array $monsterData): array
    {
        $tags = ['fiend'];

        // Detect fire immunity (common in demons and devils)
        if ($this->hasDamageImmunity($monsterData, 'fire')) {
            $tags[] = 'fire_immune';
            $this->incrementMetric('fire_immune_count');
        }

        // Detect poison immunity (common in most fiends)
        if ($this->hasDamageImmunity($monsterData, 'poison')) {
            $tags[] = 'poison_immune';
            $this->incrementMetric('poison_immune_count');
        }

        // Detect magic resistance trait
        if ($this->hasTraitContaining($traits, 'magic resistance')) {
            $tags[] = 'magic_resistance';
            $this->incrementMetric('magic_resistance_count');
        }

        // Store tags and increment enhancement counter
        $this->setMetric('tags_applied', $tags);
        $this->incrementMetric('fiends_enhanced');

        return $traits; // Traits unchanged, tags stored in metrics
    }
}
