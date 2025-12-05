<?php

namespace App\Http\Requests;

class BackgroundIndexRequest extends BaseIndexRequest
{
    /**
     * Entity-specific validation rules.
     *
     * NOTE: All filtering uses Meilisearch ?filter= parameter.
     * Legacy MySQL params (grants_proficiency, grants_skill, etc.) have been removed.
     * Common search/filter rules are inherited from BaseIndexRequest.
     */
    protected function entityRules(): array
    {
        return [];
    }

    /**
     * Sortable columns for backgrounds.
     */
    protected function getSortableColumns(): array
    {
        return ['name', 'slug'];
    }
}
