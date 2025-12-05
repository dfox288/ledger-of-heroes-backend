<?php

namespace App\Services\Importers\Strategies\Monster;

class ConstructStrategy extends AbstractMonsterStrategy
{
    /**
     * Determine if this strategy applies to constructs.
     */
    public function appliesTo(array $monsterData): bool
    {
        $type = strtolower($monsterData['type'] ?? '');

        return str_contains($type, 'construct');
    }

    /**
     * Enhance traits with construct-specific detection (immunities, constructed nature).
     */
    public function enhanceTraits(array $traits, array $monsterData): array
    {
        $this->applyConditionalTags('construct', [
            // Most constructs are poison immune
            'immunity:poison' => 'poison_immune',
            // Condition immunities (any of the common construct immunities)
            'condition:charm' => 'condition_immune',
            'condition:exhaustion' => 'condition_immune',
            'condition:frightened' => 'condition_immune',
            'condition:paralyzed' => 'condition_immune',
            'condition:petrified' => 'condition_immune',
            // Constructed Nature trait
            'trait:constructed nature' => 'constructed_nature',
            "trait:doesn't require air" => 'constructed_nature',
        ], $traits, $monsterData);

        return $traits;
    }
}
