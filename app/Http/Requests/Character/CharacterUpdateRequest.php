<?php

namespace App\Http\Requests\Character;

use Illuminate\Foundation\Http\FormRequest;

class CharacterUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],

            // Core choices
            'race_id' => ['sometimes', 'nullable', 'exists:races,id'],
            'class_id' => ['sometimes', 'nullable', 'exists:classes,id'],
            'background_id' => ['sometimes', 'nullable', 'exists:backgrounds,id'],

            // Ability scores (manual entry, range 3-20)
            'strength' => ['sometimes', 'nullable', 'integer', 'min:3', 'max:20'],
            'dexterity' => ['sometimes', 'nullable', 'integer', 'min:3', 'max:20'],
            'constitution' => ['sometimes', 'nullable', 'integer', 'min:3', 'max:20'],
            'intelligence' => ['sometimes', 'nullable', 'integer', 'min:3', 'max:20'],
            'wisdom' => ['sometimes', 'nullable', 'integer', 'min:3', 'max:20'],
            'charisma' => ['sometimes', 'nullable', 'integer', 'min:3', 'max:20'],

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
        ];
    }

    public function messages(): array
    {
        return [
            'strength.min' => 'Ability scores must be at least 3.',
            'strength.max' => 'Ability scores cannot exceed 20.',
            'dexterity.min' => 'Ability scores must be at least 3.',
            'dexterity.max' => 'Ability scores cannot exceed 20.',
            'constitution.min' => 'Ability scores must be at least 3.',
            'constitution.max' => 'Ability scores cannot exceed 20.',
            'intelligence.min' => 'Ability scores must be at least 3.',
            'intelligence.max' => 'Ability scores cannot exceed 20.',
            'wisdom.min' => 'Ability scores must be at least 3.',
            'wisdom.max' => 'Ability scores cannot exceed 20.',
            'charisma.min' => 'Ability scores must be at least 3.',
            'charisma.max' => 'Ability scores cannot exceed 20.',
        ];
    }
}
