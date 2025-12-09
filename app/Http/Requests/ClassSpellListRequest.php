<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClassSpellListRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Public API
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Pagination
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:200'],
            'page' => ['sometimes', 'integer', 'min:1'],

            // Search
            'search' => ['sometimes', 'string', 'max:255'],

            // Spell filters
            'level' => ['sometimes', 'integer', 'min:0', 'max:9'],
            'school' => ['sometimes', 'integer', 'exists:spell_schools,id'],
            'concentration' => ['sometimes', 'boolean'],
            'ritual' => ['sometimes', 'boolean'],

            // Sorting (spells table doesn't have timestamps)
            'sort_by' => ['sometimes', Rule::in(['name', 'level'])],
            'sort_direction' => ['sometimes', Rule::in(['asc', 'desc'])],
        ];
    }
}
