<?php

namespace App\Http\Requests;

use App\Models\Language;
use App\Models\Skill;
use Illuminate\Validation\Rule;

class BackgroundIndexRequest extends BaseIndexRequest
{
    /**
     * Entity-specific validation rules.
     */
    protected function entityRules(): array
    {
        return [
            // Filter by granted proficiency
            'grants_proficiency' => ['sometimes', 'string', 'max:255'],

            // Filter by granted skill
            'grants_skill' => ['sometimes', 'string', 'max:255'],

            // Filter by spoken language
            'speaks_language' => ['sometimes', 'string', 'max:255'],

            // Filter by language choice count
            'language_choice_count' => ['sometimes', 'integer', 'min:0', 'max:10'],

            // Filter entities granting any languages
            'grants_languages' => ['sometimes', Rule::in([0, 1, '0', '1', true, false, 'true', 'false'])],
        ];
    }

    /**
     * Sortable columns for backgrounds.
     */
    protected function getSortableColumns(): array
    {
        return ['name', 'created_at', 'updated_at'];
    }
}
