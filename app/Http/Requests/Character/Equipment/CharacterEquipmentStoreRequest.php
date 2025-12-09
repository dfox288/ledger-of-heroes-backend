<?php

namespace App\Http\Requests\Character\Equipment;

use App\Http\Requests\Concerns\MapsApiFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CharacterEquipmentStoreRequest extends FormRequest
{
    use MapsApiFields;

    protected array $fieldMappings = [
        'item' => 'item_slug',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->mapApiFields();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Accept 'item' as API param, mapped to item_slug
            // No exists validation - dangling references allowed per #288
            // Format: source:slug (e.g., phb:longsword)
            'item_slug' => ['nullable', 'string', 'max:150', 'regex:/^[a-z0-9-]+:[a-z0-9-]+$/i'],
            'custom_name' => ['nullable', 'string', 'max:255'],
            'custom_description' => ['nullable', 'string', 'max:2000'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'item_slug.regex' => 'The item slug must be in format source:slug (e.g., phb:longsword).',
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
