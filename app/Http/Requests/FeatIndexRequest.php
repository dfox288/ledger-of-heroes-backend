<?php

namespace App\Http\Requests;

use App\Models\AbilityScore;
use App\Models\ProficiencyType;
use App\Models\Race;
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
            // Filter by prerequisite race
            'prerequisite_race' => [
                'sometimes',
                'string',
                Rule::in($this->getCachedLookup('races', Race::class)),
            ],

            // Filter by prerequisite ability score
            'prerequisite_ability' => [
                'sometimes',
                'string',
                Rule::in($this->getCachedLookup('ability_scores', AbilityScore::class, 'code')),
            ],

            // Minimum ability score value
            'min_value' => [
                'sometimes',
                'integer',
                'min:1',
                'max:30',
            ],

            // Filter by prerequisite proficiency
            'prerequisite_proficiency' => [
                'sometimes',
                'string',
                Rule::in($this->getCachedLookup('proficiency_types', ProficiencyType::class)),
            ],

            // Filter by presence of prerequisites
            'has_prerequisites' => [
                'sometimes',
                Rule::in(['true', 'false', '1', '0', true, false, 1, 0]),
            ],

            // Filter by granted proficiency
            'grants_proficiency' => [
                'sometimes',
                'string',
                Rule::in($this->getCachedLookup('proficiency_types_grants', ProficiencyType::class)),
            ],

            // Filter by granted skill
            'grants_skill' => [
                'sometimes',
                'string',
                Rule::in($this->getCachedLookup('skills', Skill::class)),
            ],
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
