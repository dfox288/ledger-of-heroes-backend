<?php

namespace App\Http\Requests\Character;

use App\Enums\AbilityScoreMethod;
use App\Services\AbilityScoreValidatorService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CharacterUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $method = $this->getAbilityScoreMethod();

        return [
            'name' => ['sometimes', 'string', 'max:255'],

            // Core choices
            'race_id' => ['sometimes', 'nullable', 'exists:races,id'],
            'class_id' => ['sometimes', 'nullable', 'exists:classes,id'],
            'background_id' => ['sometimes', 'nullable', 'exists:backgrounds,id'],

            // Ability score method
            'ability_score_method' => ['sometimes', Rule::enum(AbilityScoreMethod::class)],

            // Ability scores - rules depend on method
            ...$this->getAbilityScoreRules($method),

            // Level
            'level' => ['sometimes', 'integer', 'min:1', 'max:20'],

            // Experience points
            'experience_points' => ['sometimes', 'integer', 'min:0'],

            // Hit points
            'max_hit_points' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'current_hit_points' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'temp_hit_points' => ['sometimes', 'integer', 'min:0'],

            // Armor class
            'armor_class' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:30'],

            // Alignment (optional, must be valid D&D alignment)
            'alignment' => ['sometimes', 'nullable', 'string', 'in:Lawful Good,Neutral Good,Chaotic Good,Lawful Neutral,True Neutral,Chaotic Neutral,Lawful Evil,Neutral Evil,Chaotic Evil,Unaligned'],

            // Inspiration (optional, boolean)
            'has_inspiration' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get ability score validation rules based on the method.
     */
    private function getAbilityScoreRules(?AbilityScoreMethod $method): array
    {
        $abilities = ['strength', 'dexterity', 'constitution', 'intelligence', 'wisdom', 'charisma'];

        return match ($method) {
            AbilityScoreMethod::PointBuy => $this->getPointBuyRules($abilities),
            AbilityScoreMethod::StandardArray => $this->getStandardArrayRules($abilities),
            default => $this->getManualRules($abilities),
        };
    }

    /**
     * Rules for point buy method: all 6 required, range 8-15.
     */
    private function getPointBuyRules(array $abilities): array
    {
        $rules = [];
        foreach ($abilities as $ability) {
            $rules[$ability] = ['required', 'integer', 'min:8', 'max:15'];
        }

        return $rules;
    }

    /**
     * Rules for standard array method: all 6 required, range 8-15.
     */
    private function getStandardArrayRules(array $abilities): array
    {
        $rules = [];
        foreach ($abilities as $ability) {
            // Standard array values are 8, 10, 12, 13, 14, 15
            $rules[$ability] = ['required', 'integer', 'min:8', 'max:15'];
        }

        return $rules;
    }

    /**
     * Rules for manual method: optional, range 3-20.
     */
    private function getManualRules(array $abilities): array
    {
        $rules = [];
        foreach ($abilities as $ability) {
            $rules[$ability] = ['sometimes', 'nullable', 'integer', 'min:3', 'max:20'];
        }

        return $rules;
    }

    /**
     * Get the ability score method from request or current character.
     */
    private function getAbilityScoreMethod(): ?AbilityScoreMethod
    {
        // Check if method is being set in this request
        if ($this->has('ability_score_method')) {
            $methodValue = $this->input('ability_score_method');

            return AbilityScoreMethod::tryFrom($methodValue);
        }

        // If no method specified and we have ability score changes, default to manual
        $abilityFields = ['strength', 'dexterity', 'constitution', 'intelligence', 'wisdom', 'charisma'];
        $hasAbilityChanges = collect($abilityFields)->contains(fn ($field) => $this->has($field));

        if ($hasAbilityChanges) {
            return AbilityScoreMethod::Manual;
        }

        // No method change and no ability changes - no validation needed
        return null;
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $method = $this->getAbilityScoreMethod();

            if ($method === null) {
                return;
            }

            $scores = $this->getAbilityScores();

            // Only validate if all scores are present
            if (count($scores) !== 6 && $method !== AbilityScoreMethod::Manual) {
                $validator->errors()->add(
                    'ability_scores',
                    'All 6 ability scores are required for '.$method->label().'.'
                );

                return;
            }

            if ($method === AbilityScoreMethod::PointBuy) {
                $this->validatePointBuy($validator, $scores);
            } elseif ($method === AbilityScoreMethod::StandardArray) {
                $this->validateStandardArray($validator, $scores);
            }
        });
    }

    /**
     * Get ability scores from the request in the format expected by the validator.
     */
    private function getAbilityScores(): array
    {
        $scores = [];

        $mapping = [
            'STR' => 'strength',
            'DEX' => 'dexterity',
            'CON' => 'constitution',
            'INT' => 'intelligence',
            'WIS' => 'wisdom',
            'CHA' => 'charisma',
        ];

        foreach ($mapping as $code => $field) {
            if ($this->has($field) && $this->input($field) !== null) {
                $scores[$code] = (int) $this->input($field);
            }
        }

        return $scores;
    }

    /**
     * Validate point buy rules.
     */
    private function validatePointBuy(Validator $validator, array $scores): void
    {
        $service = app(AbilityScoreValidatorService::class);
        $errors = $service->getPointBuyErrors($scores);

        foreach ($errors as $error) {
            $validator->errors()->add('ability_scores', $error);
        }
    }

    /**
     * Validate standard array rules.
     */
    private function validateStandardArray(Validator $validator, array $scores): void
    {
        $service = app(AbilityScoreValidatorService::class);
        $errors = $service->getStandardArrayErrors($scores);

        foreach ($errors as $error) {
            $validator->errors()->add('ability_scores', $error);
        }
    }

    public function messages(): array
    {
        return [
            'strength.required' => 'Strength is required for this ability score method.',
            'strength.min' => 'Strength must be at least :min.',
            'strength.max' => 'Strength cannot exceed :max.',
            'dexterity.required' => 'Dexterity is required for this ability score method.',
            'dexterity.min' => 'Dexterity must be at least :min.',
            'dexterity.max' => 'Dexterity cannot exceed :max.',
            'constitution.required' => 'Constitution is required for this ability score method.',
            'constitution.min' => 'Constitution must be at least :min.',
            'constitution.max' => 'Constitution cannot exceed :max.',
            'intelligence.required' => 'Intelligence is required for this ability score method.',
            'intelligence.min' => 'Intelligence must be at least :min.',
            'intelligence.max' => 'Intelligence cannot exceed :max.',
            'wisdom.required' => 'Wisdom is required for this ability score method.',
            'wisdom.min' => 'Wisdom must be at least :min.',
            'wisdom.max' => 'Wisdom cannot exceed :max.',
            'charisma.required' => 'Charisma is required for this ability score method.',
            'charisma.min' => 'Charisma must be at least :min.',
            'charisma.max' => 'Charisma cannot exceed :max.',
        ];
    }
}
