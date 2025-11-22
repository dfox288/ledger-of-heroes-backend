<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:255'],
            'types' => ['sometimes', 'array'],
            'types.*' => ['string', Rule::in(['spell', 'item', 'race', 'class', 'background', 'feat', 'monster'])],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'debug' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'q.required' => 'Please provide a search query',
            'q.min' => 'Search query must be at least 2 characters',
            'types.*.in' => 'Invalid entity type. Valid types: spell, item, race, class, background, feat, monster',
        ];
    }
}
