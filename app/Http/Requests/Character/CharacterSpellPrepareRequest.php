<?php

namespace App\Http\Requests\Character;

use Illuminate\Foundation\Http\FormRequest;

class CharacterSpellPrepareRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'class_slug' => ['sometimes', 'string'],
        ];
    }
}
