<?php

namespace App\Http\Requests\CharacterNote;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CharacterNoteUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'content' => ['sometimes', 'string', 'max:10000'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $note = $this->route('note');

            if (! $note) {
                return;
            }

            // If updating title to null/empty on a category that requires title
            if ($this->has('title') && $note->category->requiresTitle() && empty($this->input('title'))) {
                $validator->errors()->add(
                    'title',
                    "Title is required for {$note->category->label()} notes."
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'content.max' => 'Note content cannot exceed 10,000 characters.',
        ];
    }
}
