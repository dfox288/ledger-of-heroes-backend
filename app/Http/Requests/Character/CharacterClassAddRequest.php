<?php

namespace App\Http\Requests\Character;

use App\Http\Requests\Concerns\MapsApiFields;
use Illuminate\Foundation\Http\FormRequest;

class CharacterClassAddRequest extends FormRequest
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
            // Preferred API field name. Mapped to class_slug before validation.
            // @example phb:wizard
            'class' => ['required_without:class_slug', 'string', 'max:150'],

            // Internal field name (also accepted for backwards compatibility).
            // @deprecated Use 'class' instead
            'class_slug' => ['required_without:class', 'string', 'max:150'],

            'force' => ['sometimes', 'boolean'],
        ];
    }
}
