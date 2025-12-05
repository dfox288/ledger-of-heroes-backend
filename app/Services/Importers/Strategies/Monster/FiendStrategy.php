<?php

namespace App\Services\Importers\Strategies\Monster;

class FiendStrategy extends AbstractMonsterStrategy
{
    /**
     * Determine if this strategy applies to fiends (devils, demons, yugoloths).
     */
    public function appliesTo(array $monsterData): bool
    {
        $type = strtolower($monsterData['type'] ?? '');

        return str_contains($type, 'fiend')
            || str_contains($type, 'devil')
            || str_contains($type, 'demon')
            || str_contains($type, 'yugoloth');
    }

    /**
     * Enhance traits with fiend-specific detection.
     */
    public function enhanceTraits(array $traits, array $monsterData): array
    {
        $this->applyConditionalTags('fiend', [
            // Fire immunity (common in demons and devils)
            'immunity:fire' => 'fire_immune',
            // Poison immunity (common in most fiends)
            'immunity:poison' => 'poison_immune',
            // Magic resistance trait
            'trait:magic resistance' => 'magic_resistance',
        ], $traits, $monsterData);

        return $traits;
    }
}
