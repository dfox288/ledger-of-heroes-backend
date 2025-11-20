<?php

namespace App\Http\Requests;

class RaceShowRequest extends BaseShowRequest
{
    /**
     * Relationships that can be included via ?include
     */
    protected function getIncludableRelationships(): array
    {
        return [
            'sources',
            'sources.source',
            'traits',
            'proficiencies',
            'proficiencies.skill',
            'proficiencies.proficiencyType',
            'modifiers',
            'languages',
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
            'size',
            'speed',
            'created_at',
            'updated_at',
        ];
    }
}
