<?php

namespace App\Http\Requests\Character\Equipment;

use App\Enums\EquipmentLocation;
use App\Models\CharacterEquipment;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'location' => ['nullable', 'string', Rule::in(EquipmentLocation::values())],
            'is_attuned' => ['nullable', 'boolean'],
            // Prevent changing item type (database ↔ custom)
            'item_id' => ['prohibited'],
            'custom_name' => ['prohibited'],
            'custom_description' => ['prohibited'],
        ];
    }

    /**
     * Configure the validator instance with attunement and location rules.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateIsAttuned($validator);
            $this->validateLocation($validator);
        });
    }

    /**
     * Validate is_attuned field.
     */
    private function validateIsAttuned(Validator $validator): void
    {
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
    }

    /**
     * Validate location field and its constraints.
     */
    private function validateLocation(Validator $validator): void
    {
        if (! $this->has('location')) {
            return;
        }

        $location = $this->input('location');

        // Skip if location validation already failed
        if (! in_array($location, EquipmentLocation::values())) {
            return;
        }

        /** @var CharacterEquipment $equipment */
        $equipment = $this->route('equipment');
        $character = $this->route('character');
        $locationEnum = EquipmentLocation::from($location);

        // Custom items cannot be equipped
        if ($equipment->isCustomItem() && $locationEnum->isEquipped()) {
            $validator->errors()->add(
                'location',
                'Custom items cannot be equipped.'
            );

            return;
        }

        // Non-attunement items cannot go to 'attuned' location
        if ($location === EquipmentLocation::ATTUNED->value) {
            if (! $equipment->requiresAttunement()) {
                $validator->errors()->add(
                    'location',
                    'This item does not require attunement.'
                );

                return;
            }

            // Check attunement limit
            $currentlyAttuned = $character->equipment()
                ->where('id', '!=', $equipment->id)
                ->where('location', EquipmentLocation::ATTUNED->value)
                ->count();

            if ($currentlyAttuned >= EquipmentLocation::ATTUNED->maxSlots()) {
                $validator->errors()->add(
                    'location',
                    'Cannot attune to more than 3 items. Unattune from another item first.'
                );
            }
        }
    }
}
