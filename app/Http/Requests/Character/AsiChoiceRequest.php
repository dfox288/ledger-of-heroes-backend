<?php

namespace App\Http\Requests\Character;

use App\Models\Character;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AsiChoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization handled in controller via policy
        return true;
    }

    public function rules(): array
    {
        return [
            'choice_type' => ['required', 'string', Rule::in(['feat', 'ability_increase'])],

            // Feat choice
            'feat_id' => [
                'required_if:choice_type,feat',
                'nullable',
                'integer',
                'exists:feats,id',
            ],

            // Ability increase choice
            'ability_increases' => [
                'required_if:choice_type,ability_increase',
                'nullable',
                'array',
                'max:2',
            ],
            'ability_increases.*' => [
                'integer',
                'min:1',
                'max:2',
            ],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('choice_type') === 'ability_increase') {
                $this->validateAbilityIncreases($validator);
            }
        });
    }

    /**
     * Validate ability increase structure.
     */
    private function validateAbilityIncreases($validator): void
    {
        $increases = $this->input('ability_increases', []);

        if (! is_array($increases)) {
            return;
        }

        // Validate keys are valid ability codes
        $validCodes = array_keys(Character::ABILITY_SCORES);
        foreach (array_keys($increases) as $code) {
            if (! in_array(strtoupper($code), $validCodes)) {
                $validator->errors()->add(
                    'ability_increases',
                    "Invalid ability code: {$code}. Valid codes: ".implode(', ', $validCodes)
                );
            }
        }

        // Validate total is exactly 2
        $total = array_sum($increases);
        if ($total !== 2) {
            $validator->errors()->add(
                'ability_increases',
                "Ability increases must total exactly 2 points. Got: {$total}"
            );
        }
    }

    /**
     * Get the validated ability increases with normalized keys.
     *
     * @return array<string, int>
     */
    public function getAbilityIncreases(): array
    {
        $increases = $this->input('ability_increases', []);
        $normalized = [];

        foreach ($increases as $code => $value) {
            $normalized[strtoupper($code)] = (int) $value;
        }

        return $normalized;
    }

    public function messages(): array
    {
        return [
            'choice_type.required' => 'You must specify a choice type (feat or ability_increase).',
            'choice_type.in' => 'Choice type must be either "feat" or "ability_increase".',
            'feat_id.required_if' => 'A feat ID is required when choosing a feat.',
            'feat_id.exists' => 'The selected feat does not exist.',
            'ability_increases.required_if' => 'Ability increases are required when choosing ability increase.',
            'ability_increases.max' => 'You can only increase up to 2 abilities.',
            'ability_increases.*.min' => 'Each ability increase must be at least 1.',
            'ability_increases.*.max' => 'Each ability increase cannot exceed 2.',
        ];
    }
}
