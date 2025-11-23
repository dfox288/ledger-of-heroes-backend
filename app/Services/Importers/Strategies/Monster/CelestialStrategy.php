<?php

namespace App\Services\Importers\Strategies\Monster;

class CelestialStrategy extends AbstractMonsterStrategy
{
    /**
     * Determine if this strategy applies to celestials.
     */
    public function appliesTo(array $monsterData): bool
    {
        $type = strtolower($monsterData['type'] ?? '');

        return str_contains($type, 'celestial');
    }

    /**
     * Enhance actions with celestial-specific detection (radiant damage, healing).
     */
    public function enhanceActions(array $actions, array $monsterData): array
    {
        $tags = ['celestial'];
        $hasRadiant = false;
        $hasHealing = false;

        foreach ($actions as &$action) {
            $desc = strtolower($action['description'] ?? '');
            $name = strtolower($action['name'] ?? '');

            // Detect radiant damage
            if (str_contains($desc, 'radiant')) {
                $hasRadiant = true;
            }

            // Detect healing abilities
            if (str_contains($desc, 'healing') || str_contains($name, 'healing')) {
                $hasHealing = true;
            }
        }

        if ($hasRadiant) {
            $tags[] = 'radiant_damage';
            $this->incrementMetric('radiant_attackers');
        }

        if ($hasHealing) {
            $tags[] = 'healer';
            $this->incrementMetric('healers_count');
        }

        // Store tags and increment enhancement counter
        $this->setMetric('tags_applied', $tags);
        $this->incrementMetric('celestials_enhanced');

        return $actions;
    }
}
