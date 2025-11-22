<?php

namespace App\Services\Importers\Strategies\Monster;

class DragonStrategy extends AbstractMonsterStrategy
{
    public function appliesTo(array $monsterData): bool
    {
        return str_contains(strtolower($monsterData['type']), 'dragon');
    }

    public function enhanceActions(array $actions, array $monsterData): array
    {
        foreach ($actions as &$action) {
            // Detect breath weapon pattern
            if (str_contains($action['name'], 'Breath')) {
                // Extract recharge: "Fire Breath (Recharge 5-6)" → "5-6"
                if (preg_match('/\(Recharge ([\d\-]+)\)/i', $action['name'], $matches)) {
                    $action['recharge'] = $matches[1];
                }
            }
        }

        return $actions;
    }

    public function enhanceTraits(array $traits, array $monsterData): array
    {
        foreach ($traits as &$trait) {
            // Legendary Resistance (3/Day) → extract recharge
            if (str_contains($trait['name'], 'Legendary Resistance')) {
                if (preg_match('/\((\d+)\/Day\)/i', $trait['name'], $matches)) {
                    $trait['recharge'] = $matches[1].'/DAY';
                }
            }
        }

        return $traits;
    }

    public function extractMetadata(array $monsterData): array
    {
        $breathWeapons = collect($monsterData['actions'] ?? [])
            ->filter(fn ($a) => str_contains($a['name'], 'Breath'))
            ->count();

        $lairActions = collect($monsterData['legendary'] ?? [])
            ->filter(fn ($l) => ($l['category'] ?? null) === 'lair')
            ->count();

        $metadata = parent::extractMetadata($monsterData);
        $metadata['breath_weapons_detected'] = $breathWeapons;
        $metadata['legendary_resistance'] = collect($monsterData['traits'] ?? [])
            ->contains(fn ($t) => str_contains($t['name'], 'Legendary Resistance'));
        $metadata['lair_actions'] = $lairActions;

        return $metadata;
    }
}
