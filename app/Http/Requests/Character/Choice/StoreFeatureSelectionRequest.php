<?php

namespace App\Http\Requests\Character\Choice;

use App\Models\OptionalFeature;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreFeatureSelectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $character = $this->route('character');

        return [
            'optional_feature_slug' => [
                'required',
                'string',
                Rule::exists('optional_features', 'slug'),
                // Ensure the character doesn't already have this feature
                Rule::unique('feature_selections')
                    ->where('character_id', $character->id),
            ],
            'class_slug' => [
                'nullable',
                'string',
                Rule::exists('classes', 'slug'),
            ],
            'subclass_name' => ['nullable', 'string', 'max:100'],
            'level_acquired' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->any()) {
                    return;
                }

                $character = $this->route('character');
                $feature = OptionalFeature::with(['classes', 'classPivots'])
                    ->where('slug', $this->optional_feature_slug)
                    ->first();

                if (! $feature) {
                    return;
                }

                // Check level requirement
                if ($feature->level_requirement && $character->total_level < $feature->level_requirement) {
                    $validator->errors()->add(
                        'optional_feature_slug',
                        "This feature requires level {$feature->level_requirement}. Character is level {$character->total_level}."
                    );
                }

                // Check class eligibility
                if (! $this->isClassEligible($character, $feature)) {
                    $validator->errors()->add(
                        'optional_feature_slug',
                        'This character does not have the required class or subclass for this feature.'
                    );
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'optional_feature_slug.unique' => 'This character has already selected this feature.',
        ];
    }

    /**
     * Check if the character has a class/subclass that grants this feature.
     */
    private function isClassEligible($character, OptionalFeature $feature): bool
    {
        // If feature has no class associations, it's available to anyone
        if ($feature->classes->isEmpty() && $feature->classPivots->isEmpty()) {
            return true;
        }

        $characterClasses = $character->characterClasses()
            ->with(['characterClass', 'subclass'])
            ->get();

        // Check if any of the character's classes can use this feature
        foreach ($characterClasses as $charClass) {
            // Check base class eligibility
            if ($feature->classes->contains('slug', $charClass->class_slug)) {
                // Check if feature requires specific subclass
                $subclassRequirements = $feature->classPivots
                    ->where('class_slug', $charClass->class_slug)
                    ->pluck('subclass_name')
                    ->filter();

                // If no subclass requirement, base class is enough
                if ($subclassRequirements->isEmpty()) {
                    return true;
                }

                // If subclass is required, check character has it
                if ($charClass->subclass && $subclassRequirements->contains($charClass->subclass->name)) {
                    return true;
                }
            }
        }

        return false;
    }
}
