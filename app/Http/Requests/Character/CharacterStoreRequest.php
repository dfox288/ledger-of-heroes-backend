<?php

namespace App\Http\Requests\Character;

use App\Models\Character;
use Illuminate\Foundation\Http\FormRequest;

class CharacterStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],

            // Core choices (nullable for wizard-style creation)
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

            // Level (default 1)
            'level' => ['sometimes', 'integer', 'min:1', 'max:20'],

            // Alignment (optional, must be valid D&D alignment)
            'alignment' => ['sometimes', 'nullable', 'string', 'in:'.implode(',', Character::ALIGNMENTS)],

            // Inspiration (optional, boolean)
            'has_inspiration' => ['sometimes', 'boolean'],
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
