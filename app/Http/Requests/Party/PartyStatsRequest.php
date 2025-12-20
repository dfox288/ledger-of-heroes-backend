<?php

namespace App\Http\Requests\Party;

use Illuminate\Foundation\Http\FormRequest;

class PartyStatsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
