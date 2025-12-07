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

    /**
     * Map API field names to internal database column names.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('condition')) {
            $this->merge(['condition_slug' => $this->input('condition')]);
        }
    }

    public function rules(): array
    {
        return [
            // Accept 'condition' as API param, mapped to condition_slug
            // No exists validation - dangling references allowed per #288
            'condition_slug' => ['required', 'string', 'max:150'],
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
        $conditionSlug = $this->input('condition_slug');
        if (! $conditionSlug) {
            return false;
        }

        $condition = Condition::where('full_slug', $conditionSlug)->first();

        return $condition && $condition->slug !== 'exhaustion';
    }
}
