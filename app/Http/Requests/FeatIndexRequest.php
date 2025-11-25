<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class FeatIndexRequest extends BaseIndexRequest
{
    /**
     * Entity-specific validation rules.
     *
     * NOTE: All filtering uses Meilisearch ?filter= parameter.
     * Legacy MySQL params (prerequisite_race, prerequisite_ability, has_prerequisites,
     * grants_proficiency, grants_skill, etc.) are kept for backwards compatibility but deprecated.
     */
    protected function entityRules(): array
    {
        return [
            // Full-text search query
            'q' => ['sometimes', 'string', 'min:2', 'max:255'],

            // Meilisearch filter expression
            'filter' => ['sometimes', 'string', 'max:1000'],

            // === DEPRECATED LEGACY MYSQL FILTERS (kept for backwards compatibility) ===
            'prerequisite_race' => ['sometimes', 'string', 'max:255'],
            'prerequisite_ability' => ['sometimes', 'string', 'max:255'],
            'min_value' => ['sometimes', 'integer', 'min:1', 'max:30'],
            'prerequisite_proficiency' => ['sometimes', 'string', 'max:255'],
            'has_prerequisites' => ['sometimes', Rule::in([0, 1, '0', '1', true, false, 'true', 'false'])],
            'grants_proficiency' => ['sometimes', 'string', 'max:255'],
            'grants_skill' => ['sometimes', 'string', 'max:255'],
        ];
    }

    /**
     * Sortable columns for this entity.
     */
    protected function getSortableColumns(): array
    {
        return ['name', 'created_at', 'updated_at'];
    }
}
