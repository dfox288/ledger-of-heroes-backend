<?php

namespace App\Services\Importers\Strategies\Monster;

class BeastStrategy extends AbstractMonsterStrategy
{
    /**
     * Determine if this strategy applies to beasts.
     */
    public function appliesTo(array $monsterData): bool
    {
        return str_contains(strtolower($monsterData['type'] ?? ''), 'beast');
    }

    /**
     * Enhance traits with beast-specific detection.
     */
    public function enhanceTraits(array $traits, array $monsterData): array
    {
        $tags = ['beast'];

        // Keen Senses - heightened perception/tracking
        if ($this->hasTraitContaining($traits, 'keen smell')
            || $this->hasTraitContaining($traits, 'keen sight')
            || $this->hasTraitContaining($traits, 'keen hearing')) {
            $tags[] = 'keen_senses';
            $this->incrementMetric('keen_senses_count');
        }

        // Pack Tactics - cooperative hunting
        if ($this->hasTraitContaining($traits, 'pack tactics')) {
            $tags[] = 'pack_tactics';
            $this->incrementMetric('pack_tactics_count');
        }

        // Charge/Pounce - movement-based attacks
        if ($this->hasTraitContaining($traits, 'charge')
            || $this->hasTraitContaining($traits, 'pounce')
            || $this->hasTraitContaining($traits, 'trampling charge')) {
            $tags[] = 'charge';
            $this->incrementMetric('charge_count');
        }

        // Special Movement - spider climb, web walker, amphibious
        if ($this->hasTraitContaining($traits, 'spider climb')
            || $this->hasTraitContaining($traits, 'web walker')
            || $this->hasTraitContaining($traits, 'amphibious')) {
            $tags[] = 'special_movement';
            $this->incrementMetric('special_movement_count');
        }

        // Store tags and increment enhancement counter
        $this->setMetric('tags_applied', $tags);
        $this->incrementMetric('beasts_enhanced');

        return $traits;
    }
}
