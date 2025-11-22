<?php

namespace App\Services\Importers\Strategies\Monster;

class UndeadStrategy extends AbstractMonsterStrategy
{
    public function appliesTo(array $monsterData): bool
    {
        return strtolower($monsterData['type']) === 'undead';
    }

    public function enhanceTraits(array $traits, array $monsterData): array
    {
        foreach ($traits as &$trait) {
            // Detect turn resistance
            if (str_contains(strtolower($trait['description']), 'turn undead')) {
                $this->incrementMetric('turn_resistance');
            }

            // Detect sunlight sensitivity
            if (str_contains(strtolower($trait['name']), 'sunlight')) {
                $this->incrementMetric('sunlight_sensitivity');
            }
        }

        return $traits;
    }

    public function enhanceActions(array $actions, array $monsterData): array
    {
        foreach ($actions as &$action) {
            // Detect life drain pattern
            if (str_contains(strtolower($action['description']), 'necrotic damage') &&
                str_contains(strtolower($action['description']), 'hit point maximum')) {
                $this->incrementMetric('life_drain');
            }
        }

        return $actions;
    }

    public function extractMetadata(array $monsterData): array
    {
        $metadata = parent::extractMetadata($monsterData);

        // Add boolean flags for detection results
        $metadata['has_turn_resistance'] = collect($monsterData['traits'] ?? [])
            ->contains(fn ($t) => str_contains(strtolower($t['description'] ?? ''), 'turn') &&
                                 str_contains(strtolower($t['description'] ?? ''), 'undead'));

        $metadata['has_sunlight_sensitivity'] = collect($monsterData['traits'] ?? [])
            ->contains(fn ($t) => str_contains(strtolower($t['name'] ?? ''), 'sunlight'));

        $metadata['condition_immunities'] = $monsterData['condition_immunities'] ?? '';

        return $metadata;
    }
}
