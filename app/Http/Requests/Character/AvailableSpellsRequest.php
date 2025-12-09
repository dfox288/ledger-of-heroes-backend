<?php

namespace App\Http\Requests\Character;

use Illuminate\Foundation\Http\FormRequest;

class AvailableSpellsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'min_level' => ['sometimes', 'integer', 'min:0', 'max:9'],
            'max_level' => ['sometimes', 'integer', 'min:0', 'max:9'],
            'include_known' => ['sometimes', 'in:true,false,1,0'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $minLevel = $this->input('min_level');
            $maxLevel = $this->input('max_level');

            if ($minLevel !== null && $maxLevel !== null && $minLevel > $maxLevel) {
                $validator->errors()->add('min_level', 'min_level cannot be greater than max_level');
            }
        });
    }
}
