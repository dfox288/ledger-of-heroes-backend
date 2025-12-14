<?php

namespace App\Http\Requests\Party;

use Illuminate\Foundation\Http\FormRequest;

class PartyAddMonsterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'monster_id' => ['required', 'integer', 'exists:monsters,id'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ];
    }
}
