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
            // Accept 'spell' (preferred) or 'spell_slug' (backwards compat)
            // No exists validation - dangling references allowed per #288
            'spell_slug' => ['required', 'string', 'max:150'],
            'source' => ['sometimes', 'string', 'in:class,race,feat,item,other'],
        ];
    }
}
