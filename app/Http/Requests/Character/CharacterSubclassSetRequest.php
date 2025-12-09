<?php

namespace App\Http\Requests\Character;

use App\Http\Requests\Concerns\MapsApiFields;
use Illuminate\Foundation\Http\FormRequest;

class CharacterSubclassSetRequest extends FormRequest
{
    use MapsApiFields;

    protected array $fieldMappings = [
        'subclass' => 'subclass_slug',
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
            // Accept 'subclass' as API param, mapped to subclass_slug
            // No exists validation - dangling references allowed per #288
            'subclass_slug' => ['required', 'string', 'max:150'],
        ];
    }
}
