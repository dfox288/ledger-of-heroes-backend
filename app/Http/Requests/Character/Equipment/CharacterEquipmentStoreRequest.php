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
            // Preferred API field name. Mapped to item_slug before validation.
            // Nullable because equipment can be custom-only (using custom_name).
            // XOR validation handled in withValidator().
            // Format: source:slug (e.g., phb:longsword)
            // @example phb:longsword
            'item' => ['nullable', 'string', 'max:150', 'regex:/^[a-z0-9-]+:[a-z0-9-]+$/i'],

            // Internal field name (also accepted for backwards compatibility).
            // @deprecated Use 'item' instead
            'item_slug' => ['nullable', 'string', 'max:150', 'regex:/^[a-z0-9-]+:[a-z0-9-]+$/i'],
            'custom_name' => ['nullable', 'string', 'max:255'],
            'custom_description' => ['nullable', 'string', 'max:2000'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'item.regex' => 'The item must be in format source:slug (e.g., phb:longsword).',
            'item_slug.regex' => 'The item slug must be in format source:slug (e.g., phb:longsword).',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * Equipment requires either a database item (via item/item_slug) OR a custom item
     * (via custom_name), but not both. This XOR logic can't be expressed in standard
     * Laravel validation rules, so we handle it here.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $hasItemSlug = $this->filled('item_slug');
            $hasCustomName = $this->filled('custom_name');

            if (! $hasItemSlug && ! $hasCustomName) {
                $validator->errors()->add('item', 'Either item or custom_name is required.');
            }

            if ($hasItemSlug && $hasCustomName) {
                $validator->errors()->add('item', 'Cannot specify both item and custom_name.');
            }
        });
    }
}
