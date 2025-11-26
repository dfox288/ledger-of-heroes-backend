<?php

namespace App\Http\Requests;

class OptionalFeatureIndexRequest extends BaseIndexRequest
{
    /**
     * Entity-specific validation rules.
     *
     * NOTE: All filtering uses Meilisearch ?filter= parameter.
     * Legacy MySQL params have been removed.
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
     * Sortable columns for optional features.
     */
    protected function getSortableColumns(): array
    {
        return ['name', 'level_requirement', 'resource_cost', 'slug'];
    }
}
