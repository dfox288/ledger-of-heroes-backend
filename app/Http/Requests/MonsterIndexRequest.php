<?php

namespace App\Http\Requests;

class MonsterIndexRequest extends BaseIndexRequest
{
    /**
     * Entity-specific validation rules.
     *
     * Common search/filter rules are inherited from BaseIndexRequest.
     * Meilisearch filter examples:
     * - ?filter=challenge_rating >= 10
     * - ?filter=spell_slugs IN [fireball]
     * - ?filter=type = dragon AND armor_class >= 18
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
        return [
            'name',
            'challenge_rating',
            'hit_points_average',
            'armor_class',
            'experience_points',
        ];
    }
}
