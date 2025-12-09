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
            // Accept 'spell' (preferred, mapped to spell_slug) or 'spell_slug' (backwards compat)
            // No exists validation - dangling references allowed per #288
            'spell' => ['required_without:spell_slug', 'string', 'max:150'],
            'spell_slug' => ['required_without:spell', 'string', 'max:150'],
            'source' => ['sometimes', 'string', 'in:class,race,feat,item,other'],
        ];
    }
}
