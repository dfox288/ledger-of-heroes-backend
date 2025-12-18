<?php

namespace App\Http\Requests\Character;

use App\Http\Requests\BaseIndexRequest;
use Illuminate\Validation\Rule;

class CharacterIndexRequest extends BaseIndexRequest
{
    /**
     * Entity-specific validation rules.
     *
     * Supports filtering by:
     * - status: 'complete' or 'draft'
     * - class: class slug (e.g., 'phb:fighter')
     */
    protected function entityRules(): array
    {
        return [
            'status' => ['sometimes', 'string', Rule::in(['complete', 'draft'])],
            'class' => ['sometimes', 'string', 'exists:classes,slug'],
        ];
    }

    /**
     * Sortable columns for characters.
     */
    protected function getSortableColumns(): array
    {
        return ['name', 'created_at', 'updated_at'];
    }
}
