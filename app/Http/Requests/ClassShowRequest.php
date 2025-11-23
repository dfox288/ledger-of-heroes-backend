<?php

namespace App\Http\Requests;

class ClassShowRequest extends BaseShowRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'include_base_features' => ['sometimes', 'boolean'],
        ]);
    }

    /**
     * Relationships that can be included via ?include
     */
    protected function getIncludableRelationships(): array
    {
        return [
            'sources',
            'sources.source',
            'features',
            'features.randomTables',
            'features.randomTables.entries',
            'proficiencies',
            'proficiencies.skill',
            'proficiencies.proficiencyType',
            'levelProgression',
            'counters',
            'spellcastingAbility',
        ];
    }

    /**
     * Fields that can be selected via ?fields
     */
    protected function getSelectableFields(): array
    {
        return [
            'id',
            'name',
            'slug',
            'description',
            'hit_die',
            'created_at',
            'updated_at',
        ];
    }
}
