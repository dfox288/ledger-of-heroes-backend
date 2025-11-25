<?php

namespace App\Http\Requests;

class FeatShowRequest extends BaseShowRequest
{
    /**
     * Relationships that can be included via ?include
     */
    protected function getIncludableRelationships(): array
    {
        return [
            'tags',
            'sources',
            'sources.source',
            'modifiers',
            'modifiers.abilityScore',
            'modifiers.skill',
            'proficiencies',
            'proficiencies.skill',
            'proficiencies.proficiencyType',
            'conditions',
            'prerequisites',
            'prerequisites.prerequisite',
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
            'prerequisites_text',
            'created_at',
            'updated_at',
        ];
    }
}
