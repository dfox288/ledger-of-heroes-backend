<?php

namespace App\Http\Requests;

class ClassIndexRequest extends BaseIndexRequest
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

            // Class-specific filters (backwards compatibility)
            'base_only' => ['sometimes', 'boolean'],
            'grants_proficiency' => ['sometimes', 'string', 'max:255'],
            'grants_skill' => ['sometimes', 'string', 'max:255'],
            'grants_saving_throw' => ['sometimes', 'string', 'max:255'],
        ];
    }

    /**
     * Sortable columns for this entity.
     */
    protected function getSortableColumns(): array
    {
        return ['name', 'hit_die', 'created_at', 'updated_at'];
    }
}
