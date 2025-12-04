<?php

namespace App\Http\Requests\Character;

use Illuminate\Foundation\Http\FormRequest;

class SetSubclassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subclass_id' => ['required', 'integer', 'exists:classes,id'],
        ];
    }
}
