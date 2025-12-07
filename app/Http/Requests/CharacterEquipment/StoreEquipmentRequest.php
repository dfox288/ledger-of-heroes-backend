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
            'item_slug' => ['nullable', 'string', 'exists:items,full_slug'],
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
            $hasItemSlug = $this->filled('item_slug');
            $hasCustomName = $this->filled('custom_name');

            if (! $hasItemSlug && ! $hasCustomName) {
                $validator->errors()->add('item_slug', 'Either item_slug or custom_name is required.');
            }

            if ($hasItemSlug && $hasCustomName) {
                $validator->errors()->add('item_slug', 'Cannot specify both item_slug and custom_name.');
            }
        });
    }
}
