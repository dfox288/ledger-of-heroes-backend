<?php

namespace App\Http\Requests\Party;

use Illuminate\Foundation\Http\FormRequest;

class EncounterPresetStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'monsters' => ['required', 'array', 'min:1'],
            'monsters.*.monster_id' => ['required', 'integer', 'exists:monsters,id'],
            'monsters.*.quantity' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ];
    }
}
