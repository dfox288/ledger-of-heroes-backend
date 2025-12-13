<?php

namespace App\Http\Requests\Character;

use App\Models\Character;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CharacterReviveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hit_points' => ['sometimes', 'integer', 'min:1'],
            'clear_exhaustion' => ['sometimes', 'boolean'],
            'source' => ['sometimes', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $character = $this->route('character');
            if ($character instanceof Character && ! $character->is_dead) {
                $validator->errors()->add('character', 'Character is not dead');
            }
        });
    }

    public function messages(): array
    {
        return [
            'hit_points.min' => 'Hit points must be at least 1.',
        ];
    }
}
