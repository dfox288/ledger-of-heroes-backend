<?php

namespace App\Http\Requests\CharacterEquipment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreEquipmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
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
            'item_id' => ['nullable', 'integer', 'exists:items,id'],
            'custom_name' => ['nullable', 'string', 'max:255'],
            'custom_description' => ['nullable', 'string', 'max:2000'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $hasItemId = $this->filled('item_id');
            $hasCustomName = $this->filled('custom_name');

            if (! $hasItemId && ! $hasCustomName) {
                $validator->errors()->add('item_id', 'Either item_id or custom_name is required.');
            }

            if ($hasItemId && $hasCustomName) {
                $validator->errors()->add('item_id', 'Cannot specify both item_id and custom_name.');
            }
        });
    }
}
