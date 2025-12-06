<?php

namespace App\Http\Requests\Character;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
