<?php

namespace App\Http\Requests\Character;

use Illuminate\Foundation\Http\FormRequest;

class SetSubclassRequest extends FormRequest
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
        if ($this->has('subclass')) {
            $this->merge(['subclass_slug' => $this->input('subclass')]);
        }
    }

    public function rules(): array
    {
        return [
            // Accept 'subclass' as API param, mapped to subclass_slug
            // No exists validation - dangling references allowed per #288
            'subclass_slug' => ['required', 'string', 'max:150'],
        ];
    }
}
