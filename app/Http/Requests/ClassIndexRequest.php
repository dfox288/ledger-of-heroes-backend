<?php

namespace App\Http\Requests;

use App\Models\AbilityScore;
use App\Models\ProficiencyType;
use App\Models\Skill;
use Illuminate\Validation\Rule;

class ClassIndexRequest extends BaseIndexRequest
{
    /**
     * Entity-specific validation rules.
     */
    protected function entityRules(): array
    {
        return [
            'base_only' => ['sometimes', 'boolean'],
            'grants_proficiency' => [
                'sometimes',
                'string',
                Rule::in($this->getCachedLookup('proficiency_types', ProficiencyType::class)),
            ],
            'grants_skill' => [
                'sometimes',
                'string',
                Rule::in($this->getCachedLookup('skills', Skill::class)),
            ],
            'grants_saving_throw' => [
                'sometimes',
                'string',
                Rule::in($this->getCachedLookup('ability_scores', AbilityScore::class, 'code')),
            ],
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
