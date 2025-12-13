<?php

namespace App\Http\Requests\Character\Equipment;

use App\Models\CharacterEquipment;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class CharacterEquipmentUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * See GitHub Issue #197 for user authentication implementation.
     * When implemented: return $this->user()->can('update', $this->route('character'));
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
            'is_attuned' => ['nullable', 'boolean'],
            // Prevent changing item type (database ↔ custom)
            'item_id' => ['prohibited'],
            'custom_name' => ['prohibited'],
            'custom_description' => ['prohibited'],
        ];
    }

    /**
     * Configure the validator instance with attunement rules.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->has('is_attuned') || ! $this->boolean('is_attuned')) {
                return;
            }

            /** @var CharacterEquipment $equipment */
            $equipment = $this->route('equipment');

            // Check if item requires attunement
            if (! $equipment->requiresAttunement()) {
                $validator->errors()->add(
                    'is_attuned',
                    'This item does not require attunement.'
                );

                return;
            }

            // Check if already at attunement limit (3 slots)
            $character = $this->route('character');
            $currentlyAttuned = $character->equipment()
                ->where('id', '!=', $equipment->id)
                ->where('is_attuned', true)
                ->count();

            if ($currentlyAttuned >= 3) {
                $validator->errors()->add(
                    'is_attuned',
                    'Cannot attune to more than 3 items. Unattune from another item first.'
                );
            }
        });
    }
}
