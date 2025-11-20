<?php

namespace App\Http\Requests;

class ItemShowRequest extends BaseShowRequest
{
    /**
     * Relationships that can be included via ?include
     */
    protected function getIncludableRelationships(): array
    {
        return [
            'sources',
            'sources.source',
            'modifiers',
            'abilities',
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
            'type',
            'rarity',
            'description',
            'magic',
            'attunement',
            'strength_requirement',
            'created_at',
            'updated_at',
        ];
    }
}
