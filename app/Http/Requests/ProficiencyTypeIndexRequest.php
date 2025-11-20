<?php

namespace App\Http\Requests;

class ProficiencyTypeIndexRequest extends BaseLookupIndexRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'category' => ['sometimes', 'string', 'max:255'],
            'subcategory' => ['sometimes', 'string', 'max:255'],
        ]);
    }
}
