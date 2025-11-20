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
            'grants_proficiency' => ['sometimes', 'string', 'max:255'],
            'grants_skill' => ['sometimes', 'string', 'max:255'],
            'grants_saving_throw' => ['sometimes', 'string', 'max:255'],
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
