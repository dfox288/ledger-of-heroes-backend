<?php

namespace App\Http\Requests\Character\Equipment;

use App\Enums\EquipmentLocation;
use App\Models\CharacterEquipment;
use App\Services\EquipmentManagerService;
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
     * Configure the validator instance with attunement, location, and two-handed rules.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateIsAttuned($validator);
            $this->validateLocation($validator);
            $this->validateTwoHandedWeapon($validator);
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
        $locationEnum = EquipmentLocation::from($location);

        // Custom items cannot be equipped
        if ($equipment->isCustomItem() && $locationEnum->isEquipped()) {
            $validator->errors()->add(
                'location',
                'Custom items cannot be equipped.'
            );

            return;
        }
    }

    /**
     * Validate two-handed weapon restrictions.
     *
     * - Cannot equip to off_hand if main_hand has a two-handed weapon
     * - When equipping two-handed weapon to main_hand, off_hand is auto-cleared by service
     */
    private function validateTwoHandedWeapon(Validator $validator): void
    {
        if (! $this->has('location')) {
            return;
        }

        $location = $this->input('location');

        // Only check when equipping to off_hand
        if ($location !== EquipmentLocation::OFF_HAND->value) {
            return;
        }

        // Skip if location validation already failed
        if (! in_array($location, EquipmentLocation::values())) {
            return;
        }

        $character = $this->route('character');

        // Check if character has a two-handed weapon in main hand
        $equipmentService = app(EquipmentManagerService::class);
        if ($equipmentService->hasTwoHandedWeaponEquipped($character)) {
            $validator->errors()->add(
                'location',
                'Cannot use off-hand while wielding a two-handed weapon.'
            );
        }
    }
}
