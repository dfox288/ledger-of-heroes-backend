<?php

namespace App\Http\Requests;

class SpellShowRequest extends BaseShowRequest
{
    /**
     * Relationships that can be included via ?include
     */
    protected function getIncludableRelationships(): array
    {
        return [
            'spellSchool',
            'sources',
            'sources.source',
            'effects',
            'effects.damageType',
            'classes',
            'tags',
            'savingThrows',
            'randomTables',
            'randomTables.entries',
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
            'level',
            'description',
            'casting_time',
            'range',
            'components',
            'material_components',
            'duration',
            'needs_concentration',
            'is_ritual',
            'higher_levels',
            'created_at',
            'updated_at',
        ];
    }
}
