<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class ItemIndexRequest extends BaseIndexRequest
{
    /**
     * Entity-specific validation rules.
     */
    protected function entityRules(): array
    {
        return [
            // Search query (Scout/Meilisearch)
            'q' => ['sometimes', 'string', 'min:2', 'max:255'],

            // Meilisearch filter expression
            'filter' => ['sometimes', 'string', 'max:1000'],

            // Item-specific filters (backwards compatibility)
            'min_strength' => ['sometimes', 'integer', 'min:1', 'max:30'],
            'has_prerequisites' => ['sometimes', Rule::in([0, 1, '0', '1', true, false, 'true', 'false'])],

            // Spell filtering (similar to Monster)
            'spells' => ['sometimes', 'string', 'max:500'],
            'spells_operator' => ['sometimes', 'string', 'in:AND,OR'],
            'spell_level' => ['sometimes', 'integer', 'min:0', 'max:9'],

            // Item type filter
            'type' => ['sometimes', 'string', 'max:10'],

            // Has charges filter
            'has_charges' => ['sometimes', Rule::in([0, 1, '0', '1', true, false, 'true', 'false'])],

            // Rarity filter
            'rarity' => ['sometimes', 'string', 'max:20'],
        ];
    }

    /**
     * Sortable columns for this entity.
     */
    protected function getSortableColumns(): array
    {
        return ['name', 'type', 'rarity', 'created_at', 'updated_at'];
    }
}
