<?php

namespace App\Http\Requests\Character;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Resolve a pending character choice.
 *
 * This request supports multiple formats depending on the choice type:
 *
 * **Generic Selection** (most choice types):
 * ```json
 * {"selected": ["option1", "option2"]}
 * ```
 * The `selected` array contains IDs or slugs of the chosen options.
 *
 * **ASI Choice** (Ability Score Improvement):
 * ```json
 * {"type": "asi", "increases": {"strength": 2, "dexterity": 1}}
 * ```
 * The `increases` object maps ability names to increase amounts (1 or 2).
 *
 * **Feat Choice**:
 * ```json
 * {"type": "feat", "feat": "phb:alert"}
 * ```
 * The `feat` is the full_slug of the chosen feat.
 */
class ResolveChoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization handled in controller via policy
        return true;
    }

    /**
     * Map API field names to internal database column names.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('feat')) {
            $this->merge(['feat_slug' => $this->input('feat')]);
        }
    }

    public function rules(): array
    {
        return [
            // Generic selection (array of slugs)
            'selected' => ['sometimes', 'array'],
            'selected.*' => ['required_with:selected', 'string'],

            // ASI/Feat specific fields
            'type' => ['sometimes', 'string', Rule::in(['asi', 'feat'])],
            // Accept 'feat' as API param, mapped to feat_slug
            // No exists validation - dangling references allowed per #288
            'feat_slug' => ['required_if:type,feat', 'string', 'max:150'],
            'increases' => ['required_if:type,asi', 'array'],
            'increases.*' => ['integer', 'min:1', 'max:2'],
        ];
    }

    public function messages(): array
    {
        return [
            'selected.array' => 'Selection must be an array of choices.',
            'type.in' => 'Type must be either "asi" or "feat".',
            'feat_slug.required_if' => 'Feat slug is required when type is "feat".',
            'increases.required_if' => 'Ability score increases are required when type is "asi".',
            'increases.*.min' => 'Each ability increase must be at least 1.',
            'increases.*.max' => 'Each ability increase cannot exceed 2.',
        ];
    }
}
