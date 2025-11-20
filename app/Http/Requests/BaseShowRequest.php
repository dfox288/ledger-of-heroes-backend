<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class BaseShowRequest extends FormRequest
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
            // Relationship inclusion
            'include' => ['sometimes', 'array'],
            'include.*' => [Rule::in($this->getIncludableRelationships())],

            // Sparse fieldsets
            'fields' => ['sometimes', 'array'],
            'fields.*' => [Rule::in($this->getSelectableFields())],
        ];
    }

    /**
     * Relationships that can be included via ?include
     */
    abstract protected function getIncludableRelationships(): array;

    /**
     * Fields that can be selected via ?fields
     */
    abstract protected function getSelectableFields(): array;
}
