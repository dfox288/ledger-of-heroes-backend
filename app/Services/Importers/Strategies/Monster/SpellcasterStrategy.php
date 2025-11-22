<?php

namespace App\Services\Importers\Strategies\Monster;

use App\Models\Monster;

class SpellcasterStrategy extends AbstractMonsterStrategy
{
    public function appliesTo(array $monsterData): bool
    {
        return isset($monsterData['spells']) && ! empty($monsterData['spells']);
    }

    public function afterCreate(Monster $monster, array $monsterData): void
    {
        // TODO: Implement spell syncing in future iteration
        // For now, just mark that strategy was selected
    }

    public function extractMetadata(array $monsterData): array
    {
        $metadata = parent::extractMetadata($monsterData);
        $metadata['has_spells'] = ! empty($monsterData['spells']);
        $metadata['has_spell_slots'] = ! empty($monsterData['slots']);

        return $metadata;
    }
}
