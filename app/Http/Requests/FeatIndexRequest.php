<?php

namespace App\Http\Requests;

class FeatIndexRequest extends BaseIndexRequest
{
    /**
     * Entity-specific validation rules.
     *
     * NOTE: All filtering uses Meilisearch ?filter= parameter.
     * Legacy MySQL params have been removed.
     * Common search/filter rules are inherited from BaseIndexRequest.
     */
    protected function entityRules(): array
    {
        return [];
    }

    /**
     * Sortable columns for this entity.
     */
    protected function getSortableColumns(): array
    {
        return ['name', 'slug'];
    }
}
