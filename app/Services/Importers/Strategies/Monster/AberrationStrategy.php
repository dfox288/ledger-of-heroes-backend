<?php

namespace App\Services\Importers\Strategies\Monster;

class AberrationStrategy extends AbstractMonsterStrategy
{
    /**
     * Determine if this strategy applies to aberrations.
     */
    public function appliesTo(array $monsterData): bool
    {
        return str_contains(strtolower($monsterData['type'] ?? ''), 'aberration');
    }

    /**
     * Enhance traits with aberration-specific detection (telepathy, antimagic, mind control).
     */
    public function enhanceTraits(array $traits, array $monsterData): array
    {
        $tags = ['aberration'];
        $languages = strtolower($monsterData['languages'] ?? '');

        // Telepathy detection (common in aberrations)
        if (str_contains($languages, 'telepathy')) {
            $tags[] = 'telepathy';
            $this->incrementMetric('telepaths');
        }

        // Antimagic detection (beholder antimagic cone)
        if ($this->hasTraitContaining($traits, 'antimagic')) {
            $tags[] = 'antimagic';
            $this->incrementMetric('antimagic_users');
        }

        // Mind control in traits (dominate, enslave)
        if ($this->hasTraitContaining($traits, 'dominate')
            || $this->hasTraitContaining($traits, 'enslave')) {
            $tags[] = 'mind_control';
            $this->incrementMetric('mind_controllers');
        }

        $this->setMetric('tags_applied', $tags);

        return $traits;
    }

    /**
     * Enhance actions with aberration-specific detection (psychic damage, mind control).
     */
    public function enhanceActions(array $actions, array $monsterData): array
    {
        $tags = $this->metrics['tags_applied'] ?? ['aberration'];

        // Psychic damage detection
        foreach ($actions as $action) {
            $desc = strtolower($action['description'] ?? '');

            if (str_contains($desc, 'psychic damage')) {
                $tags[] = 'psychic_damage';
                $this->incrementMetric('psychic_attackers');
                break; // Only tag once
            }

            // Mind control in actions (charm, dominated)
            if (! in_array('mind_control', $tags)
                && (str_contains($desc, 'charm') || str_contains($desc, 'dominated'))) {
                $tags[] = 'mind_control';
                $this->incrementMetric('mind_controllers');
            }
        }

        $this->setMetric('tags_applied', $tags);
        $this->incrementMetric('aberrations_enhanced');

        return $actions;
    }
}
