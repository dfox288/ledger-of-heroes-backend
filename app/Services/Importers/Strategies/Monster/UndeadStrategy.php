<?php

namespace App\Services\Importers\Strategies\Monster;

class UndeadStrategy extends AbstractMonsterStrategy
{
    public function appliesTo(array $monsterData): bool
    {
        return strtolower($monsterData['type']) === 'undead';
    }

    public function extractMetadata(array $monsterData): array
    {
        return [
            'has_turn_resistance' => collect($monsterData['traits'] ?? [])
                ->contains(fn ($t) => str_contains(strtolower($t['name'] ?? ''), 'turn resistance')
                    || str_contains(strtolower($t['description'] ?? ''), 'turn undead')),
            'has_sunlight_sensitivity' => collect($monsterData['traits'] ?? [])
                ->contains(fn ($t) => str_contains(strtolower($t['name'] ?? ''), 'sunlight')),
            'condition_immunities' => $monsterData['conditionImmune'] ?? '',
        ];
    }
}
