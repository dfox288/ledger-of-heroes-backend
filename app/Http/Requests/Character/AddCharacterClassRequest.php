<?php

namespace App\Http\Requests\Character;

use App\Http\Requests\Concerns\MapsApiFields;
use Illuminate\Foundation\Http\FormRequest;

class AddCharacterClassRequest extends FormRequest
{
    use MapsApiFields;

    protected array $fieldMappings = [
        'class' => 'class_slug',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->mapApiFields();
    }

    public function rules(): array
    {
        return [
            // Accept 'class' as API param, mapped to class_slug
            // No exists validation - dangling references allowed per #288
            'class_slug' => ['required', 'string', 'max:150'],
            'force' => ['sometimes', 'boolean'],
        ];
    }
}
