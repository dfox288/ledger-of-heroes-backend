<?php

namespace App\Http\Requests;

use App\Models\Language;
use App\Models\ProficiencyType;
use App\Models\Skill;
use Illuminate\Validation\Rule;

class RaceIndexRequest extends BaseIndexRequest
{
    /**
     * Entity-specific validation rules.
     */
    protected function entityRules(): array
    {
        return [
            // Filter by granted proficiency
            'grants_proficiency' => [
                'sometimes',
                'string',
                Rule::in($this->getCachedLookup('proficiency_types', ProficiencyType::class)),
            ],

            // Filter by granted skill
            'grants_skill' => [
                'sometimes',
                'string',
                Rule::in($this->getCachedLookup('skills', Skill::class)),
            ],

            // Filter by proficiency type/category
            'grants_proficiency_type' => [
                'sometimes',
                'string',
                Rule::in($this->getCachedLookup('proficiency_types', ProficiencyType::class)),
            ],

            // Filter by spoken language
            'speaks_language' => [
                'sometimes',
                'string',
                Rule::in($this->getCachedLookup('languages', Language::class)),
            ],

            // Filter by language choice count
            'language_choice_count' => [
                'sometimes',
                'integer',
                'min:0',
                'max:10',
            ],

            // Filter entities granting any languages
            'grants_languages' => [
                'sometimes',
                Rule::in(['true', 'false', '1', '0', 1, 0, true, false]),
            ],
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
