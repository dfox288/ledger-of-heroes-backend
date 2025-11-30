<?php

namespace App\Http\Requests\Character;

use Illuminate\Foundation\Http\FormRequest;

class CharacterShowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
