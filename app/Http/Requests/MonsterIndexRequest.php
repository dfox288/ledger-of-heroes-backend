<?php

namespace App\Http\Requests;

class MonsterIndexRequest extends BaseIndexRequest
{
    /**
     * Entity-specific validation rules.
     */
    protected function entityRules(): array
    {
        return [
            // Full-text search query
            'q' => ['sometimes', 'string', 'min:2', 'max:255'],

            // Meilisearch filter expression
            // Examples:
            // - ?filter=challenge_rating >= 10
            // - ?filter=spell_slugs IN [fireball]
            // - ?filter=type = dragon AND armor_class >= 18
            'filter' => ['sometimes', 'string', 'max:1000'],
        ];
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
