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
            // Search query
            'q' => ['sometimes', 'string', 'min:2', 'max:255'],

            // Challenge rating filters
            'challenge_rating' => ['sometimes', 'numeric', 'min:0'],
            'min_cr' => ['sometimes', 'numeric', 'min:0'],
            'max_cr' => ['sometimes', 'numeric', 'min:0'],

            // Type filter (dragon, humanoid, undead, etc.)
            'type' => ['sometimes', 'string', 'max:50'],

            // Size filter (T, S, M, L, H, G)
            'size' => ['sometimes', 'string', 'max:2'],

            // Alignment filter
            'alignment' => ['sometimes', 'string', 'max:50'],

            // Spell filter (comma-separated spell slugs)
            'spells' => ['sometimes', 'string', 'max:500'],

            // Meilisearch filter expression (for future use)
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
