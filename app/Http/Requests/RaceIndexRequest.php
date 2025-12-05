<?php

namespace App\Http\Requests;

class RaceIndexRequest extends BaseIndexRequest
{
    /**
     * Entity-specific validation rules.
     *
     * NOTE: All filtering uses Meilisearch ?filter= parameter.
     * Legacy MySQL params (spells, spell_level, has_innate_spells, ability_bonus, size, etc.) have been removed.
     * Common search/filter rules are inherited from BaseIndexRequest.
     */
    protected function entityRules(): array
    {
        return [];
    }

    /**
     * Sortable columns for this entity.
     */
    protected function getSortableColumns(): array
    {
        return ['name', 'size', 'speed', 'slug'];
    }
}
