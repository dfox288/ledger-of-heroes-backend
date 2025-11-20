<?php

namespace App\Http\Requests;

class SpellIndexRequest extends BaseIndexRequest
{
    /**
     * Entity-specific validation rules.
     */
    protected function entityRules(): array
    {
        return [
            // Spell-specific filters
            'level' => ['sometimes', 'integer', 'min:0', 'max:9'],
            'school' => ['sometimes', 'integer', 'exists:spell_schools,id'],
            'concentration' => ['sometimes', 'boolean'],
            'ritual' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Sortable columns for spells.
     */
    protected function getSortableColumns(): array
    {
        return ['name', 'level', 'created_at', 'updated_at'];
    }
}
