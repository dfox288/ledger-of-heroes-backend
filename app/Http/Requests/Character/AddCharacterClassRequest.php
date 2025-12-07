<?php

namespace App\Http\Requests\Character;

use Illuminate\Foundation\Http\FormRequest;

class AddCharacterClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Map API field names to internal database column names.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('class')) {
            $this->merge(['class_slug' => $this->input('class')]);
        }
    }

    public function rules(): array
    {
        return [
            // Accept 'class' as API param, mapped to class_slug
            // No exists validation - dangling references allowed per #288
            'class_slug' => ['required', 'string', 'max:150'],
            'force' => ['sometimes', 'boolean'],
        ];
    }
}
