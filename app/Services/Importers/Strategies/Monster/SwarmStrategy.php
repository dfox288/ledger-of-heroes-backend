<?php

namespace App\Services\Importers\Strategies\Monster;

class SwarmStrategy extends AbstractMonsterStrategy
{
    public function appliesTo(array $monsterData): bool
    {
        return str_contains(strtolower($monsterData['type']), 'swarm');
    }

    public function extractMetadata(array $monsterData): array
    {
        // Extract individual creature size from type: "swarm of Medium beasts" → "Medium"
        $individualSize = null;
        if (preg_match('/swarm of (\w+)/i', $monsterData['type'], $matches)) {
            $individualSize = $matches[1];
        }

        return [
            'individual_creature_size' => $individualSize,
            'swarm_size' => $monsterData['size'],
        ];
    }
}
