<?php

namespace App\Http\Requests\Character\Combat;

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
            // Require either spent or action, but not both
            $hasSpent = $this->has('spent');
            $hasAction = $this->has('action');

            if (! $hasSpent && ! $hasAction) {
                $validator->errors()->add('spent', 'Either spent or action is required.');
            }

            if ($hasSpent && $hasAction) {
                $validator->errors()->add('spent', 'Cannot provide both spent and action.');
            }

            // Note: spent > max_slots validation is done in the controller
            // after the slot is found or created, since the slot may not exist yet
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
}
