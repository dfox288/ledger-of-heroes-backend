<?php

namespace App\Services\Importers\Strategies\Monster;

class ElementalStrategy extends AbstractMonsterStrategy
{
    /**
     * Determine if this strategy applies to elementals.
     */
    public function appliesTo(array $monsterData): bool
    {
        return str_contains(strtolower($monsterData['type'] ?? ''), 'elemental');
    }

    /**
     * Enhance traits with elemental-specific detection (subtypes and immunities).
     */
    public function enhanceTraits(array $traits, array $monsterData): array
    {
        $tags = ['elemental'];
        $name = strtolower($monsterData['name'] ?? '');
        $languages = strtolower($monsterData['languages'] ?? '');

        // Fire elemental detection
        if (str_contains($name, 'fire')
            || $this->hasDamageImmunity($monsterData, 'fire')
            || str_contains($languages, 'ignan')) {
            $tags[] = 'fire_elemental';
            $this->incrementMetric('fire_elementals');
        }

        // Water elemental detection
        if (str_contains($name, 'water')
            || str_contains($languages, 'aquan')) {
            $tags[] = 'water_elemental';
            $this->incrementMetric('water_elementals');
        }

        // Earth elemental detection
        if (str_contains($name, 'earth')
            || str_contains($languages, 'terran')) {
            $tags[] = 'earth_elemental';
            $this->incrementMetric('earth_elementals');
        }

        // Air elemental detection
        if (str_contains($name, 'air')
            || str_contains($languages, 'auran')) {
            $tags[] = 'air_elemental';
            $this->incrementMetric('air_elementals');
        }

        // Most elementals are poison immune
        if ($this->hasDamageImmunity($monsterData, 'poison')) {
            $tags[] = 'poison_immune';
        }

        // Store tags and increment enhancement counter
        $this->setMetric('tags_applied', $tags);
        $this->incrementMetric('elementals_enhanced');

        return $traits;
    }
}
