<?php

namespace App\Services\Importers\Strategies\Monster;

class SwarmStrategy extends AbstractMonsterStrategy
{
    public function appliesTo(array $monsterData): bool
    {
        return str_contains(strtolower($monsterData['type']), 'swarm');
    }

    public function enhanceTraits(array $traits, array $monsterData): array
    {
        foreach ($traits as &$trait) {
            // Detect swarm damage resistance pattern
            if (str_contains(strtolower($trait['description']), 'resistant to') ||
                str_contains(strtolower($trait['name']), 'swarm')) {
                $this->incrementMetric('swarm_traits');
            }
        }

        return $traits;
    }

    public function extractMetadata(array $monsterData): array
    {
        $metadata = parent::extractMetadata($monsterData);

        // Extract individual creature size from type: "swarm of Medium beasts" → "Medium"
        $individualSize = null;
        if (preg_match('/swarm of (\w+)/i', $monsterData['type'], $matches)) {
            $individualSize = $matches[1];
        }

        $metadata['individual_creature_size'] = $individualSize;
        $metadata['swarm_size'] = $monsterData['size'];

        return $metadata;
    }
}
