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
            // Preferred API field name. Mapped to subclass_slug before validation.
            // @example phb:champion
            'subclass' => ['required_without:subclass_slug', 'string', 'max:150'],

            // Internal field name (also accepted for backwards compatibility).
            // @deprecated Use 'subclass' instead
            'subclass_slug' => ['required_without:subclass', 'string', 'max:150'],
        ];
    }
}
