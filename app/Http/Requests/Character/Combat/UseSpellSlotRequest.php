<?php

namespace App\Http\Requests\Character\Combat;

use Illuminate\Foundation\Http\FormRequest;

class UseSpellSlotRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'spell_level' => ['required', 'integer', 'min:1', 'max:9'],
            'slot_type' => ['required', 'string', 'in:standard,pact_magic'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'spell_level.min' => 'Spell level must be at least 1.',
            'spell_level.max' => 'Spell level cannot exceed 9.',
            'slot_type.in' => 'Slot type must be either "standard" or "pact_magic".',
        ];
    }
}
