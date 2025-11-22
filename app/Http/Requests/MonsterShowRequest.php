<?php

namespace App\Http\Requests;

class MonsterShowRequest extends BaseShowRequest
{
    /**
     * Relationships that can be included via ?include
     */
    protected function getIncludableRelationships(): array
    {
        return [
            'size',
            'traits',
            'actions',
            'legendaryActions',
            'spellcasting',
            'sources',
            'sources.source',
            'modifiers',
            'modifiers.abilityScore',
            'modifiers.skill',
            'modifiers.damageType',
            'conditions',
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
            'size_id',
            'type',
            'alignment',
            'armor_class',
            'armor_type',
            'hit_points_average',
            'hit_dice',
            'speed_walk',
            'speed_fly',
            'speed_swim',
            'speed_burrow',
            'speed_climb',
            'can_hover',
            'strength',
            'dexterity',
            'constitution',
            'intelligence',
            'wisdom',
            'charisma',
            'challenge_rating',
            'experience_points',
            'description',
        ];
    }
}
