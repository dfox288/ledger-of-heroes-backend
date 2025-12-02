<?php

namespace App\Http\Requests\CharacterEquipment;

use Illuminate\Foundation\Http\FormRequest;

class StoreEquipmentRequest extends FormRequest
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
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
