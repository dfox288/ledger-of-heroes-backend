<?php

namespace App\Http\Requests;

class ClassIndexRequest extends BaseIndexRequest
{
    /**
     * Entity-specific validation rules.
     *
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
        return ['name', 'hit_die', 'slug'];
    }
}
