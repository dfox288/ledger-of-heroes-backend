<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OptionalFeatureShowRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Public API
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'include' => ['sometimes', 'array'],
            'include.*' => ['string'],
        ];
    }
}
