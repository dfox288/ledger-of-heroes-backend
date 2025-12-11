<?php

namespace App\Http\Requests\Character\Condition;

use App\Http\Requests\Concerns\MapsApiFields;
use App\Models\Condition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CharacterConditionStoreRequest extends FormRequest
{
    use MapsApiFields;

    protected array $fieldMappings = [
        'condition' => 'condition_slug',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->mapApiFields();
    }

    public function rules(): array
    {
        return [
            // Preferred API field name. Mapped to condition_slug before validation.
            // @example phb:poisoned
            'condition' => ['required_without:condition_slug', 'string', 'max:150'],

            // Internal field name (also accepted for backwards compatibility).
            // @deprecated Use 'condition' instead
            'condition_slug' => ['required_without:condition', 'string', 'max:150'],
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

        $condition = Condition::where('slug', $conditionSlug)->first();

        return $condition && $condition->slug !== 'exhaustion';
    }
}
