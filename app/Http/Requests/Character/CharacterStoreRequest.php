<?php

namespace App\Http\Requests\Character;

use App\Http\Requests\Concerns\MapsApiFields;
use App\Models\Character;
use Illuminate\Foundation\Http\FormRequest;

class CharacterStoreRequest extends FormRequest
{
    use MapsApiFields;

    protected array $fieldMappings = [
        'race' => 'race_slug',
        'class' => 'class_slug',
        'background' => 'background_slug',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->mapApiFields();
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

            // Preferred API field names for entity references.
            // These are mapped to *_slug fields before validation.
            // @example phb:elf
            'race' => ['sometimes', 'nullable', 'string', 'max:150'],
            // @example phb:wizard
            'class' => ['sometimes', 'nullable', 'string', 'max:150'],
            // @example phb:sage
            'background' => ['sometimes', 'nullable', 'string', 'max:150'],

            // Internal field names (also accepted for backwards compatibility).
            // Prefer using 'race', 'class', 'background' above instead.
            // @deprecated Use 'race' instead
            'race_slug' => ['sometimes', 'nullable', 'string', 'max:150'],
            // @deprecated Use 'class' instead
            'class_slug' => ['sometimes', 'nullable', 'string', 'max:150'],
            // @deprecated Use 'background' instead
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

            // Physical description fields (all optional strings)
            'age' => ['sometimes', 'nullable', 'string', 'max:50'],
            'height' => ['sometimes', 'nullable', 'string', 'max:50'],
            'weight' => ['sometimes', 'nullable', 'string', 'max:50'],
            'eye_color' => ['sometimes', 'nullable', 'string', 'max:50'],
            'hair_color' => ['sometimes', 'nullable', 'string', 'max:50'],
            'skin_color' => ['sometimes', 'nullable', 'string', 'max:50'],

            // Religious affiliation (optional)
            'deity' => ['sometimes', 'nullable', 'string', 'max:150'],

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
