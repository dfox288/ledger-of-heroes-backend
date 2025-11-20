<?php

namespace App\Http\Requests;

class SkillIndexRequest extends BaseLookupIndexRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            // Filter by ability score code
            'ability' => ['sometimes', 'string', 'exists:ability_scores,code'],
        ]);
    }
}
