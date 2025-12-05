<?php

namespace App\Http\Requests;

class SpellIndexRequest extends BaseIndexRequest
{
    /**
     * Entity-specific validation rules.
     *
     * NOTE: All filtering uses Meilisearch ?filter= parameter.
     * Legacy MySQL params (level, school, concentration, ritual, damage_type, etc.) have been removed.
     * Common search/filter rules are inherited from BaseIndexRequest.
     */
    protected function entityRules(): array
    {
        return [];
    }

    /**
     * Sortable columns for spells.
     */
    protected function getSortableColumns(): array
    {
        return ['name', 'level', 'slug'];
    }
}
