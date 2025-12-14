<?php

namespace App\Http\Requests\Character;

use Illuminate\Foundation\Http\FormRequest;

class CharacterSpellUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'is_prepared' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'is_prepared.required' => 'The is_prepared field is required.',
            'is_prepared.boolean' => 'The is_prepared field must be true or false.',
        ];
    }
}
