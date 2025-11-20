<?php

namespace App\Http\Requests;

class ClassShowRequest extends BaseShowRequest
{
    /**
     * Relationships that can be included via ?include
     */
    protected function getIncludableRelationships(): array
    {
        return [
            'sources',
            'sources.source',
            'features',
            'proficiencies',
            'proficiencies.skill',
            'proficiencies.proficiencyType',
            'levelProgression',
            'counters',
            'spellcastingAbility',
        ];
    }

    /**
     * Fields that can be selected via ?fields
     */
    protected function getSelectableFields(): array
    {
        return [
            'id',
            'name',
            'slug',
            'description',
            'hit_die',
            'created_at',
            'updated_at',
        ];
    }
}
