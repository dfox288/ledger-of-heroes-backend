<?php

namespace App\Http\Requests\Character;

use App\Http\Requests\BaseIndexRequest;

class CharacterIndexRequest extends BaseIndexRequest
{
    /**
     * Entity-specific validation rules.
     *
     * Characters use standard pagination inherited from BaseIndexRequest.
     */
    protected function entityRules(): array
    {
        return [];
    }

    /**
     * Sortable columns for characters.
     */
    protected function getSortableColumns(): array
    {
        return ['name', 'created_at', 'updated_at'];
    }
}
