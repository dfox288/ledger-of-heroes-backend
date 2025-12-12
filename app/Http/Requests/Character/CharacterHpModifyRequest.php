<?php

namespace App\Http\Requests\Character;

use Illuminate\Foundation\Http\FormRequest;

class CharacterHpModifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // hp: "-12" (damage), "+15" (heal), "45" (set)
            'hp' => ['sometimes', 'string', 'regex:/^[+-]?\d+$/'],
            // temp_hp: always absolute value, integer >= 0
            'temp_hp' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'hp.regex' => 'HP must be a number optionally prefixed with + or - (e.g., "-12", "+15", "45").',
            'temp_hp.min' => 'Temporary HP cannot be negative.',
            'temp_hp.integer' => 'Temporary HP must be an integer.',
        ];
    }

    /**
     * Parse the HP value into operation type and amount.
     *
     * @return array{type: string, value: int}|null
     */
    public function parseHpChange(): ?array
    {
        $hp = $this->input('hp');

        if ($hp === null) {
            return null;
        }

        $hp = (string) $hp;

        if (str_starts_with($hp, '-')) {
            return [
                'type' => 'damage',
                'value' => abs((int) $hp),
            ];
        }

        if (str_starts_with($hp, '+')) {
            return [
                'type' => 'heal',
                'value' => (int) ltrim($hp, '+'),
            ];
        }

        return [
            'type' => 'set',
            'value' => (int) $hp,
        ];
    }
}
