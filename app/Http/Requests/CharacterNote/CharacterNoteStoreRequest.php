<?php

namespace App\Http\Requests\CharacterNote;

use App\Enums\NoteCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CharacterNoteStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['required', Rule::enum(NoteCategory::class)],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:10000'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $category = NoteCategory::tryFrom($this->input('category'));

            if ($category && $category->requiresTitle() && empty($this->input('title'))) {
                $validator->errors()->add(
                    'title',
                    "Title is required for {$category->label()} notes."
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'category.required' => 'Note category is required.',
            'content.required' => 'Note content is required.',
            'content.max' => 'Note content cannot exceed 10,000 characters.',
        ];
    }
}
