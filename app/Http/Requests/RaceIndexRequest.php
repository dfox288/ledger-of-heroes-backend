<?php

namespace App\Http\Requests;

use App\Models\Language;
use App\Models\Skill;
use Illuminate\Validation\Rule;

class RaceIndexRequest extends BaseIndexRequest
{
    /**
     * Entity-specific validation rules.
     */
    protected function entityRules(): array
    {
        return [
            // Search query (Scout/Meilisearch)
            'q' => ['sometimes', 'string', 'min:2', 'max:255'],

            // Meilisearch filter expression
            'filter' => ['sometimes', 'string', 'max:1000'],

            // Race-specific filters (backwards compatibility)
            'grants_proficiency' => ['sometimes', 'string', 'max:255'],

            // Filter by granted skill
            'grants_skill' => ['sometimes', 'string', 'max:255'],

            // Filter by proficiency type/category
            'grants_proficiency_type' => ['sometimes', 'string', 'max:255'],

            // Filter by spoken language
            'speaks_language' => ['sometimes', 'string', 'max:255'],

            // Filter by language choice count
            'language_choice_count' => ['sometimes', 'integer', 'min:0', 'max:10'],

            // Filter entities granting any languages
            'grants_languages' => ['sometimes', Rule::in([0, 1, '0', '1', true, false, 'true', 'false'])],

            // Spell filtering
            'spells' => ['sometimes', 'string', 'max:500'],
            'spells_operator' => ['sometimes', 'string', Rule::in(['AND', 'OR'])],
            'spell_level' => ['sometimes', 'integer', 'min:0', 'max:9'],
            'has_innate_spells' => ['sometimes', Rule::in([0, 1, '0', '1', true, false, 'true', 'false'])],

            // Entity-specific filters
            'ability_bonus' => ['sometimes', 'string', Rule::in(['STR', 'DEX', 'CON', 'INT', 'WIS', 'CHA'])],
            'size' => ['sometimes', 'string', Rule::in(['T', 'S', 'M', 'L', 'H', 'G'])],
            'min_speed' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'has_darkvision' => ['sometimes', Rule::in([0, 1, '0', '1', true, false, 'true', 'false'])],
        ];
    }

    /**
     * Sortable columns for this entity.
     */
    protected function getSortableColumns(): array
    {
        return ['name', 'size', 'speed', 'created_at', 'updated_at'];
    }
}
