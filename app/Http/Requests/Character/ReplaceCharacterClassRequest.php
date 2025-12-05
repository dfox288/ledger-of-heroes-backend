<?php

namespace App\Http\Requests\Character;

use Illuminate\Foundation\Http\FormRequest;

class ReplaceCharacterClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'force' => ['sometimes', 'boolean'],
        ];
    }
}
