<?php

namespace App\Http\Requests\CharacterCondition;

use App\Models\Condition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCharacterConditionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'condition_id' => ['required', 'integer', Rule::exists('conditions', 'id')],
            'level' => [
                'nullable',
                'integer',
                'min:1',
                'max:6',
                Rule::prohibitedIf(fn () => $this->isNotExhaustion()),
            ],
            'source' => ['nullable', 'string', 'max:255'],
            'duration' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'level.prohibited' => 'Level can only be set for exhaustion conditions.',
        ];
    }

    private function isNotExhaustion(): bool
    {
        if (! $this->condition_id) {
            return false;
        }

        $condition = Condition::find($this->condition_id);

        return $condition && $condition->slug !== 'exhaustion';
    }
}
