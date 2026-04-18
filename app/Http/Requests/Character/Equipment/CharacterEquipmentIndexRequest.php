<?php

namespace App\Http\Requests\Character\Equipment;

use Illuminate\Foundation\Http\FormRequest;

class CharacterEquipmentIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'exclude_currency' => ['sometimes', 'boolean'],
        ];
    }
}
