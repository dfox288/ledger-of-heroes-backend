<?php

namespace App\Http\Requests\Party;

use App\Models\Party;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PartyAddCharacterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Party $party */
        $party = $this->route('party');

        return [
            'character_id' => [
                'required',
                'integer',
                'exists:characters,id',
                Rule::unique('party_characters')->where(function ($query) use ($party) {
                    return $query->where('party_id', $party->id);
                }),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'character_id.unique' => 'This character is already in the party.',
        ];
    }
}
