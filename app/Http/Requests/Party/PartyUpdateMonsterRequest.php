<?php

namespace App\Http\Requests\Party;

use Illuminate\Foundation\Http\FormRequest;

class PartyUpdateMonsterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => ['sometimes', 'string', 'max:255'],
            'current_hp' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
