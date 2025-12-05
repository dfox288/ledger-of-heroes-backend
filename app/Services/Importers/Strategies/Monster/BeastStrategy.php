<?php

namespace App\Services\Importers\Strategies\Monster;

class BeastStrategy extends AbstractMonsterStrategy
{
    /**
     * Determine if this strategy applies to beasts.
     */
    public function appliesTo(array $monsterData): bool
    {
        return str_contains(strtolower($monsterData['type'] ?? ''), 'beast');
    }

    /**
     * Enhance traits with beast-specific detection.
     */
    public function enhanceTraits(array $traits, array $monsterData): array
    {
        $this->applyConditionalTags('beast', [
            // Keen Senses - heightened perception/tracking
            'trait:keen smell' => 'keen_senses',
            'trait:keen sight' => 'keen_senses',
            'trait:keen hearing' => 'keen_senses',
            // Pack Tactics - cooperative hunting
            'trait:pack tactics' => 'pack_tactics',
            // Charge/Pounce - movement-based attacks
            'trait:charge' => 'charge',
            'trait:pounce' => 'charge',
            'trait:trampling charge' => 'charge',
            // Special Movement - spider climb, web walker, amphibious
            'trait:spider climb' => 'special_movement',
            'trait:web walker' => 'special_movement',
            'trait:amphibious' => 'special_movement',
        ], $traits, $monsterData);

        return $traits;
    }
}
