<?php

namespace App\Http\Requests\Character;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCounterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'spent' => [
                'nullable',
                'integer',
                'min:0',
                'prohibits:action',
            ],
            'action' => [
                'nullable',
                'string',
                'in:use,restore,reset',
                'prohibits:spent',
            ],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Require at least one of spent or action
            if (! $this->has('spent') && ! $this->has('action')) {
                $validator->errors()->add('spent', 'Either spent or action is required.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'spent.min' => 'The spent value must be at least 0.',
            'spent.prohibits' => 'Cannot specify both spent and action.',
            'action.in' => 'Action must be one of: use, restore, reset.',
            'action.prohibits' => 'Cannot specify both action and spent.',
        ];
    }
}
