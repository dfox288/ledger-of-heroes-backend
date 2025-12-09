<?php

namespace App\Http\Requests\Character\Combat;

use Illuminate\Foundation\Http\FormRequest;

class SpendHitDiceRequest extends FormRequest
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
            'die_type' => ['required', 'string', 'in:d6,d8,d10,d12'],
            'quantity' => ['required', 'integer', 'min:1'],
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
            'die_type.in' => 'The die type must be one of: d6, d8, d10, d12.',
            'quantity.min' => 'You must spend at least 1 hit die.',
        ];
    }
}
