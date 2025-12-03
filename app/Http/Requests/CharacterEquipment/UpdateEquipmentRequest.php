<?php

namespace App\Http\Requests\CharacterEquipment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEquipmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * TODO: Add proper authorization when user authentication is implemented.
     * Example: return $this->user()->can('update', $this->route('character'));
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'equipped' => ['nullable', 'boolean'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'location' => ['nullable', 'string', 'max:255'],
            // Prevent changing item type (database ↔ custom)
            'item_id' => ['prohibited'],
            'custom_name' => ['prohibited'],
            'custom_description' => ['prohibited'],
        ];
    }
}
