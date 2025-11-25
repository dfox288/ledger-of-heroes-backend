<?php

namespace App\Http\Requests;

class ItemShowRequest extends BaseShowRequest
{
    /**
     * Relationships that can be included via ?include
     */
    protected function getIncludableRelationships(): array
    {
        return [
            'sources',
            'sources.source',
            'modifiers',
            'abilities',
            'prerequisites',
            'prerequisites.prerequisite',
            'spells',
            'spells.spellSchool',
            'tags',
            'savingThrows',
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
            'type',
            'rarity',
            'description',
            'magic',
            'attunement',
            'strength_requirement',
            'created_at',
            'updated_at',
            'item_type_id',
            'detail',
            'cost_cp',
            'weight',
            'damage_dice',
            'versatile_damage',
            'damage_type_id',
            'range_normal',
            'range_long',
            'armor_class',
            'stealth_disadvantage',
            'charges_max',
            'recharge_formula',
            'recharge_timing',
        ];
    }
}
