<?php

namespace App\Http\Requests\Character;

use App\Models\Character;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CharacterDeathSaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'roll' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'damage' => ['sometimes', 'integer', 'min:1'],
            'is_critical' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Must have either roll or damage
            if (! $this->has('roll') && ! $this->has('damage')) {
                $validator->errors()->add('roll', 'Either roll or damage must be provided.');
            }

            // Can't have both
            if ($this->has('roll') && $this->has('damage')) {
                $validator->errors()->add('roll', 'Cannot provide both roll and damage.');
            }

            // Character must be at 0 HP
            $character = $this->route('character');
            if ($character instanceof Character && $character->current_hit_points > 0) {
                $validator->errors()->add('character', 'Character is not at 0 HP');
            }
        });
    }

    public function messages(): array
    {
        return [
            'roll.min' => 'Roll must be between 1 and 20.',
            'roll.max' => 'Roll must be between 1 and 20.',
            'damage.min' => 'Damage must be at least 1.',
        ];
    }
}
