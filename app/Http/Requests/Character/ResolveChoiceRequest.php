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
 * {"type": "feat", "feat_id": 42}
 * ```
 * The `feat_id` is the ID of the chosen feat.
 */
class ResolveChoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization handled in controller via policy
        return true;
    }

    public function rules(): array
    {
        return [
            // Generic selection (array of IDs or slugs)
            'selected' => ['sometimes', 'array'],
            'selected.*' => ['required_with:selected', 'string'],

            // ASI/Feat specific fields
            'type' => ['sometimes', 'string', Rule::in(['asi', 'feat'])],
            'feat_id' => ['required_if:type,feat', 'integer', 'exists:feats,id'],
            'increases' => ['required_if:type,asi', 'array'],
            'increases.*' => ['integer', 'min:1', 'max:2'],
        ];
    }

    public function messages(): array
    {
        return [
            'selected.array' => 'Selection must be an array of choices.',
            'type.in' => 'Type must be either "asi" or "feat".',
            'feat_id.required_if' => 'Feat ID is required when type is "feat".',
            'feat_id.exists' => 'The selected feat does not exist.',
            'increases.required_if' => 'Ability score increases are required when type is "asi".',
            'increases.*.min' => 'Each ability increase must be at least 1.',
            'increases.*.max' => 'Each ability increase cannot exceed 2.',
        ];
    }
}
