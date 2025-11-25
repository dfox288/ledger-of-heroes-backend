<?php

namespace App\Http\Requests;

class SpellIndexRequest extends BaseIndexRequest
{
    /**
     * Entity-specific validation rules.
     *
     * NOTE: All filtering uses Meilisearch ?filter= parameter.
     * Legacy MySQL params (level, school, concentration, ritual, damage_type, etc.) have been removed.
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
     * Sortable columns for spells.
     */
    protected function getSortableColumns(): array
    {
        return ['name', 'level', 'created_at', 'updated_at'];
    }
}
