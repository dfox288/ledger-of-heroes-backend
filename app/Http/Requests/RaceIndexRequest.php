<?php

namespace App\Http\Requests;

class RaceIndexRequest extends BaseIndexRequest
{
    /**
     * Entity-specific validation rules.
     *
     * NOTE: All filtering uses Meilisearch ?filter= parameter.
     * Legacy MySQL params (spells, spell_level, has_innate_spells, ability_bonus, size, etc.) have been removed.
     */
    protected function entityRules(): array
    {
        return [
            // Full-text search query
            'q' => ['sometimes', 'string', 'min:2', 'max:255'],

            // Meilisearch filter expression
            'filter' => ['sometimes', 'string', 'max:1000'],
        ];
    }

    /**
     * Sortable columns for this entity.
     */
    protected function getSortableColumns(): array
    {
        return ['name', 'size', 'speed', 'created_at', 'updated_at'];
    }
}
