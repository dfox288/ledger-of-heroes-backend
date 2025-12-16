<?php

namespace App\Http\Requests\Character;

use Illuminate\Foundation\Http\FormRequest;

class AddExperienceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:0'],
            'auto_level' => ['sometimes', 'boolean'],
        ];
    }
}
