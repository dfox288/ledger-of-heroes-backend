<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BaseLookupIndexRequest extends FormRequest
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
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],

            // Search
            'search' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
