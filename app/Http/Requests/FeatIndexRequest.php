<?php

namespace App\Http\Requests;

use App\Models\Skill;
use Illuminate\Validation\Rule;

class FeatIndexRequest extends BaseIndexRequest
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

            // Feat-specific filters (backwards compatibility)
            'prerequisite_race' => ['sometimes', 'string', 'max:255'],

            // Filter by prerequisite ability score
            'prerequisite_ability' => ['sometimes', 'string', 'max:255'],

            // Minimum ability score value
            'min_value' => ['sometimes', 'integer', 'min:1', 'max:30'],

            // Filter by prerequisite proficiency
            'prerequisite_proficiency' => ['sometimes', 'string', 'max:255'],

            // Filter by presence of prerequisites
            'has_prerequisites' => ['sometimes', Rule::in([0, 1, '0', '1', true, false, 'true', 'false'])],

            // Filter by granted proficiency
            'grants_proficiency' => ['sometimes', 'string', 'max:255'],

            // Filter by granted skill
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
