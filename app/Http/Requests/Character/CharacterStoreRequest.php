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

    /**
     * Map API field names to internal database column names.
     */
    protected function prepareForValidation(): void
    {
        $mappings = [
            'race' => 'race_slug',
            'class' => 'class_slug',
            'background' => 'background_slug',
        ];

        foreach ($mappings as $api => $db) {
            if ($this->has($api)) {
                $this->merge([$db => $this->input($api)]);
            }
        }
    }

    public function rules(): array
    {
        return [
            // URL-safe public identifier (required, client-generated)
            // Format: {adjective}-{noun}-{4-char-suffix} e.g., "shadow-warden-q3x9"
            'public_id' => [
                'required',
                'string',
                'max:30',
                'unique:characters,public_id',
                'regex:/^[a-z]+-[a-z]+-[A-Za-z0-9]{4}$/',
            ],

            'name' => ['required', 'string', 'max:255'],

            // Core choices (nullable for wizard-style creation)
            // Accept API names (race, class, background) mapped to *_slug columns
            // No exists validation - dangling references allowed per #288
            'race_slug' => ['sometimes', 'nullable', 'string', 'max:150'],
            'class_slug' => ['sometimes', 'nullable', 'string', 'max:150'],
            'background_slug' => ['sometimes', 'nullable', 'string', 'max:150'],

            // Ability scores (manual entry, range 3-20)
            'strength' => ['sometimes', 'nullable', 'integer', 'min:3', 'max:20'],
            'dexterity' => ['sometimes', 'nullable', 'integer', 'min:3', 'max:20'],
            'constitution' => ['sometimes', 'nullable', 'integer', 'min:3', 'max:20'],
            'intelligence' => ['sometimes', 'nullable', 'integer', 'min:3', 'max:20'],
            'wisdom' => ['sometimes', 'nullable', 'integer', 'min:3', 'max:20'],
            'charisma' => ['sometimes', 'nullable', 'integer', 'min:3', 'max:20'],

            // Level (default 1)
            'level' => ['sometimes', 'integer', 'min:1', 'max:20'],

            // Death saves
            'death_save_successes' => ['sometimes', 'integer', 'min:0', 'max:3'],
            'death_save_failures' => ['sometimes', 'integer', 'min:0', 'max:3'],

            // Alignment (optional, must be valid D&D alignment)
            'alignment' => ['sometimes', 'nullable', 'string', 'in:'.implode(',', Character::ALIGNMENTS)],

            // Inspiration (optional, boolean)
            'has_inspiration' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'public_id.required' => 'A public ID is required for character creation.',
            'public_id.unique' => 'This public ID is already in use. Please generate a new one.',
            'public_id.regex' => 'Public ID must be in format: adjective-noun-XXXX (e.g., shadow-warden-q3x9)',
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
