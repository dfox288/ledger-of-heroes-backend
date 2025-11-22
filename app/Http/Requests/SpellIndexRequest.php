<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class SpellIndexRequest extends BaseIndexRequest
{
    /**
     * Entity-specific validation rules.
     */
    protected function entityRules(): array
    {
        return [
            // Search query (Scout/Meilisearch)
            'q' => ['sometimes', 'string', 'min:2', 'max:255'],

            // Meilisearch filter expression (NEW)
            'filter' => ['sometimes', 'string', 'max:1000'],

            // Spell-specific filters (backwards compatibility)
            'level' => ['sometimes', 'integer', 'min:0', 'max:9'],
            'school' => ['sometimes', 'integer', 'exists:spell_schools,id'],
            'concentration' => ['sometimes', Rule::in([true, false, 1, 0, '1', '0', 'true', 'false'])],
            'ritual' => ['sometimes', Rule::in([true, false, 1, 0, '1', '0', 'true', 'false'])],

            // Damage/Effect filtering (NEW)
            'damage_type' => ['sometimes', 'string', 'max:255'],
            'saving_throw' => ['sometimes', 'string', 'max:255'],

            // Component filtering (NEW)
            'requires_verbal' => ['sometimes', Rule::in([0, 1, '0', '1', true, false, 'true', 'false'])],
            'requires_somatic' => ['sometimes', Rule::in([0, 1, '0', '1', true, false, 'true', 'false'])],
            'requires_material' => ['sometimes', Rule::in([0, 1, '0', '1', true, false, 'true', 'false'])],
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
