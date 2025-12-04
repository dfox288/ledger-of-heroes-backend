<?php

namespace App\Http\Requests\CharacterCondition;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCharacterConditionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'condition_id' => ['required', 'integer', Rule::exists('conditions', 'id')],
            'level' => ['nullable', 'integer', 'min:1', 'max:6'],
            'source' => ['nullable', 'string', 'max:255'],
            'duration' => ['nullable', 'string', 'max:255'],
        ];
    }
}
