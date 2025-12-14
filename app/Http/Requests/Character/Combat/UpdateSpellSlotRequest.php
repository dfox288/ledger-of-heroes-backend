<?php

namespace App\Http\Requests\Character\Combat;

use App\Models\CharacterSpellSlot;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateSpellSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'spent' => ['integer', 'min:0'],
            'action' => ['string', 'in:use,restore,reset'],
            'slot_type' => ['string', 'in:standard,pact_magic'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Require either spent or action
            if (! $this->has('spent') && ! $this->has('action')) {
                $validator->errors()->add('spent', 'Either spent or action is required.');
            }

            // Validate spent doesn't exceed total
            if ($this->has('spent')) {
                $slot = $this->getSpellSlot();
                if ($slot && $this->spent > $slot->max_slots) {
                    $validator->errors()->add(
                        'spent',
                        "Spent cannot exceed total slots ({$slot->max_slots})."
                    );
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'spent.min' => 'Spent cannot be negative.',
            'action.in' => 'Action must be one of: use, restore, reset.',
            'slot_type.in' => 'Slot type must be either "standard" or "pact_magic".',
        ];
    }

    /**
     * Get the spell slot for validation.
     */
    protected function getSpellSlot(): ?CharacterSpellSlot
    {
        $character = $this->route('character');
        $level = $this->route('level');
        $slotType = $this->input('slot_type', 'standard');

        return CharacterSpellSlot::where('character_id', $character->id)
            ->where('spell_level', $level)
            ->where('slot_type', $slotType)
            ->first();
    }
}
