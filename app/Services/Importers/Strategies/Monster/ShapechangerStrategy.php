<?php

namespace App\Services\Importers\Strategies\Monster;

class ShapechangerStrategy extends AbstractMonsterStrategy
{
    /**
     * Determine if this strategy applies to shapechangers (cross-cutting).
     */
    public function appliesTo(array $monsterData): bool
    {
        $type = strtolower($monsterData['type'] ?? '');

        // Check type field for explicit (shapechanger) marker
        return str_contains($type, 'shapechanger');
    }

    /**
     * Enhance traits with shapechanger-specific detection (lycanthropes, mimics, doppelgangers).
     */
    public function enhanceTraits(array $traits, array $monsterData): array
    {
        $tags = ['shapechanger'];
        $type = strtolower($monsterData['type'] ?? '');
        $name = strtolower($monsterData['name'] ?? '');

        // Lycanthrope detection (werewolves, wereboars, etc.)
        if (str_contains($type, 'lycanthr')
            || str_contains($name, 'were')
            || $this->hasTraitContaining($traits, 'lycanthropy')
            || $this->hasTraitContaining($traits, 'curse of lycanthropy')) {
            $tags[] = 'lycanthrope';
            $this->incrementMetric('lycanthropes');
        }

        // Mimic detection (adhesive, false appearance)
        if (str_contains($name, 'mimic')
            || $this->hasTraitContaining($traits, 'adhesive')
            || ($this->hasTraitContaining($traits, 'false appearance')
                && str_contains($type, 'monstrosity'))) {
            $tags[] = 'mimic';
            $this->incrementMetric('mimics');
        }

        // Doppelganger detection
        if (str_contains($name, 'doppelganger')
            || $this->hasTraitContaining($traits, 'read thoughts')) {
            $tags[] = 'doppelganger';
            $this->incrementMetric('doppelgangers');
        }

        // Store tags and increment enhancement counter
        $this->setMetric('tags_applied', $tags);
        $this->incrementMetric('shapechangers_enhanced');

        return $traits;
    }
}
