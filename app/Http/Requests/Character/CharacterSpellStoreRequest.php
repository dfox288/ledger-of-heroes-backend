<?php

namespace App\Http\Requests\Character;

use App\Http\Requests\Concerns\MapsApiFields;
use Illuminate\Foundation\Http\FormRequest;

class CharacterSpellStoreRequest extends FormRequest
{
    use MapsApiFields;

    protected array $fieldMappings = [
        'spell' => 'spell_slug',
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
            // Preferred API field name. Mapped to spell_slug before validation.
            // @example phb:fireball
            'spell' => ['required_without:spell_slug', 'string', 'max:150'],

            // Internal field name (also accepted for backwards compatibility).
            // @deprecated Use 'spell' instead
            'spell_slug' => ['required_without:spell', 'string', 'max:150'],

            'source' => ['sometimes', 'string', 'in:class,race,feat,item,other'],
        ];
    }
}
