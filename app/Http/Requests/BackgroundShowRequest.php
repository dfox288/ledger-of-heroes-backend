<?php

namespace App\Http\Requests;

class BackgroundShowRequest extends BaseShowRequest
{
    /**
     * Relationships that can be included via ?include parameter.
     */
    protected function getIncludableRelationships(): array
    {
        return [
            'sources',
            'sources.source',
            'traits',
            'traits.dataTables',
            'traits.dataTables.entries',
            'proficiencies',
            'proficiencies.skill',
            'proficiencies.proficiencyType',
            'languages',
            'languages.language',
        ];
    }

    /**
     * Fields that can be selected via ?fields parameter.
     */
    protected function getSelectableFields(): array
    {
        return [
            'id',
            'name',
            'slug',
            'description',
            'created_at',
            'updated_at',
        ];
    }
}
