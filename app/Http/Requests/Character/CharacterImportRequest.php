<?php

namespace App\Http\Requests\Character;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate character import request data.
 */
class CharacterImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'format_version' => ['required', 'string', 'in:1.0'],
            'character' => ['required', 'array'],
            'character.public_id' => ['required', 'string', 'max:255'],
            'character.name' => ['required', 'string', 'max:255'],
            'character.race' => ['nullable', 'string', 'max:255'],
            'character.background' => ['nullable', 'string', 'max:255'],
            'character.alignment' => ['nullable', 'string', 'max:50'],

            // Ability scores
            'character.ability_scores' => ['required', 'array'],
            'character.ability_scores.strength' => ['nullable', 'integer', 'min:1', 'max:30'],
            'character.ability_scores.dexterity' => ['nullable', 'integer', 'min:1', 'max:30'],
            'character.ability_scores.constitution' => ['nullable', 'integer', 'min:1', 'max:30'],
            'character.ability_scores.intelligence' => ['nullable', 'integer', 'min:1', 'max:30'],
            'character.ability_scores.wisdom' => ['nullable', 'integer', 'min:1', 'max:30'],
            'character.ability_scores.charisma' => ['nullable', 'integer', 'min:1', 'max:30'],

            // Classes
            'character.classes' => ['array'],
            'character.classes.*.class' => ['required', 'string', 'max:255'],
            'character.classes.*.subclass' => ['nullable', 'string', 'max:255'],
            'character.classes.*.level' => ['required', 'integer', 'min:1', 'max:20'],
            'character.classes.*.is_primary' => ['boolean'],
            'character.classes.*.hit_dice_spent' => ['integer', 'min:0'],

            // Spells
            'character.spells' => ['array'],
            'character.spells.*.spell' => ['required', 'string', 'max:255'],
            'character.spells.*.source' => ['nullable', 'string', 'max:50'],
            'character.spells.*.preparation_status' => ['nullable', 'string', 'in:known,prepared,always_prepared'],
            'character.spells.*.level_acquired' => ['nullable', 'integer', 'min:1'],

            // Equipment
            'character.equipment' => ['array'],
            'character.equipment.*.item' => ['nullable', 'string', 'max:255'],
            'character.equipment.*.custom_name' => ['nullable', 'string', 'max:255'],
            'character.equipment.*.custom_description' => ['nullable', 'string'],
            'character.equipment.*.quantity' => ['integer', 'min:1'],
            'character.equipment.*.equipped' => ['boolean'],
            'character.equipment.*.location' => ['nullable', 'string', 'max:50'],

            // Languages
            'character.languages' => ['array'],
            'character.languages.*.language' => ['required', 'string', 'max:255'],
            'character.languages.*.source' => ['nullable', 'string', 'max:50'],

            // Proficiencies
            'character.proficiencies' => ['array'],
            'character.proficiencies.skills' => ['array'],
            'character.proficiencies.skills.*.skill' => ['required', 'string', 'max:255'],
            'character.proficiencies.skills.*.source' => ['nullable', 'string', 'max:50'],
            'character.proficiencies.skills.*.expertise' => ['boolean'],
            'character.proficiencies.types' => ['array'],
            'character.proficiencies.types.*.type' => ['required', 'string', 'max:255'],
            'character.proficiencies.types.*.source' => ['nullable', 'string', 'max:50'],
            'character.proficiencies.types.*.expertise' => ['boolean'],

            // Conditions
            'character.conditions' => ['array'],
            'character.conditions.*.condition' => ['required', 'string', 'max:255'],
            'character.conditions.*.level' => ['nullable', 'integer', 'min:1'],
            'character.conditions.*.source' => ['nullable', 'string', 'max:255'],
            'character.conditions.*.duration' => ['nullable', 'string', 'max:255'],

            // Feature selections
            'character.feature_selections' => ['array'],
            'character.feature_selections.*.feature' => ['required', 'string', 'max:255'],
            'character.feature_selections.*.class' => ['nullable', 'string', 'max:255'],
            'character.feature_selections.*.subclass_name' => ['nullable', 'string', 'max:255'],
            'character.feature_selections.*.level_acquired' => ['nullable', 'integer', 'min:1'],
            'character.feature_selections.*.uses_remaining' => ['nullable', 'integer', 'min:0'],
            'character.feature_selections.*.max_uses' => ['nullable', 'integer', 'min:0'],

            // Notes
            'character.notes' => ['array'],
            'character.notes.*.category' => ['required', 'string', 'max:50'],
            'character.notes.*.title' => ['nullable', 'string', 'max:255'],
            'character.notes.*.content' => ['nullable', 'string'],
            'character.notes.*.sort_order' => ['integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'format_version.in' => 'Unsupported format version. Only version 1.0 is supported.',
            'character.required' => 'Character data is required.',
        ];
    }

    /**
     * Add custom validation to ensure proficiency fields are mutually exclusive.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator) {
            $proficiencies = $this->input('character.proficiencies', []);

            // Skill proficiencies should not have a 'type' field
            foreach ($proficiencies['skills'] ?? [] as $index => $skill) {
                if (isset($skill['type'])) {
                    $validator->errors()->add(
                        "character.proficiencies.skills.{$index}.type",
                        'Skill proficiencies should not have a type field.'
                    );
                }
            }

            // Type proficiencies should not have a 'skill' field
            foreach ($proficiencies['types'] ?? [] as $index => $type) {
                if (isset($type['skill'])) {
                    $validator->errors()->add(
                        "character.proficiencies.types.{$index}.skill",
                        'Type proficiencies should not have a skill field.'
                    );
                }
            }
        });
    }
}
