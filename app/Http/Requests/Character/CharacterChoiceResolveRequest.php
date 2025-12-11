<?php

namespace App\Http\Requests\Character;

use App\Http\Requests\Concerns\MapsApiFields;
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
 * **Equipment Choice with Category Options**:
 * ```json
 * {"selected": ["b"], "item_selections": {"b": ["phb:drum"]}}
 * ```
 * When an equipment option is a category (e.g., "any musical instrument"),
 * use `item_selections` to specify which item(s) from that category.
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
 * The `feat` is the slug of the chosen feat.
 */
class CharacterChoiceResolveRequest extends FormRequest
{
    use MapsApiFields;

    protected array $fieldMappings = [
        'feat' => 'feat_slug',
    ];

    public function authorize(): bool
    {
        // Authorization handled in controller via policy
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->mapApiFields();
    }

    public function rules(): array
    {
        $selected = $this->input('selected', []);

        return [
            // Generic selection (array of slugs)
            'selected' => ['sometimes', 'array'],
            'selected.*' => ['required_with:selected', 'string'],

            // Equipment choice: specific item selections for category options
            // Maps option letter to array of item slugs: {"b": ["phb:drum"]}
            'item_selections' => ['sometimes', 'array'],
            'item_selections.*' => [
                'array',
                'min:1', // Cannot be empty array
                function ($attribute, $value, $fail) use ($selected) {
                    // Extract option key from attribute (e.g., "item_selections.b" -> "b")
                    $optionKey = str_replace('item_selections.', '', $attribute);
                    if (! in_array($optionKey, $selected, true)) {
                        $fail("The item_selections key '{$optionKey}' must be in the selected array.");
                    }
                },
            ],
            'item_selections.*.*' => ['string', 'max:150'],

            // Equipment mode gold amount (when selecting gold instead of equipment)
            // Max 500 covers highest possible roll: 5d4 * 10 = 200 max, with buffer for edge cases
            'gold_amount' => ['sometimes', 'integer', 'min:1', 'max:500'],

            // ASI/Feat specific fields
            'type' => ['sometimes', 'string', Rule::in(['asi', 'feat'])],

            // Preferred API field name for feat selection. Mapped to feat_slug before validation.
            // Only relevant when type='feat'. The mapping happens before validation,
            // so feat becomes feat_slug which is then validated by required_if below.
            // @example phb:alert
            'feat' => ['sometimes', 'string', 'max:150'],

            // Internal field name (validated when type='feat').
            // If user sends 'feat', it's mapped to 'feat_slug' before this rule runs.
            // @deprecated Use 'feat' instead
            'feat_slug' => ['required_if:type,feat', 'string', 'max:150'],
            'increases' => ['required_if:type,asi', 'array'],
            'increases.*' => ['integer', 'min:1', 'max:2'],
        ];
    }

    public function messages(): array
    {
        return [
            'selected.array' => 'Selection must be an array of choices.',
            'item_selections.*.min' => 'Each item_selections entry must contain at least one item slug.',
            'type.in' => 'Type must be either "asi" or "feat".',
            'feat_slug.required_if' => 'Feat slug is required when type is "feat".',
            'increases.required_if' => 'Ability score increases are required when type is "asi".',
            'increases.*.min' => 'Each ability increase must be at least 1.',
            'increases.*.max' => 'Each ability increase cannot exceed 2.',
        ];
    }
}
