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
            // Spell-specific filters
            'level' => ['sometimes', 'integer', 'min:0', 'max:9'],
            'school' => ['sometimes', 'integer', 'exists:spell_schools,id'],
            'concentration' => ['sometimes', Rule::in([true, false, 1, 0, '1', '0', 'true', 'false'])],
            'ritual' => ['sometimes', Rule::in([true, false, 1, 0, '1', '0', 'true', 'false'])],
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
