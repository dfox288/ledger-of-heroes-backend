<?php

namespace App\Models\Concerns;

use App\Models\EntitySpell;
use App\Models\Spell;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Trait HasEntitySpells
 *
 * Provides spell relationships for entities that grant innate spells.
 * Used by: Race, Feat
 *
 * Two relationships are provided:
 * - spells(): Returns Spell models directly (consistent with Monster/Item API)
 * - entitySpellRecords(): Returns EntitySpell pivot records (for accessing pivot data like level_requirement)
 */
trait HasEntitySpells
{
    /**
     * Get spells granted by this entity.
     *
     * Returns Spell models directly via the polymorphic entity_spells pivot table.
     * Consistent naming with Monster::spells() and Item::spells() for unified API access.
     */
    public function spells(): MorphToMany
    {
        return $this->morphToMany(
            Spell::class,
            'reference',
            'entity_spells',
            'reference_id',
            'spell_id'
        )->withPivot([
            'ability_score_id',
            'level_requirement',
            'usage_limit',
            'is_cantrip',
            'is_choice',
            'choice_count',
            'choice_group',
            'max_level',
            'school_id',
            'class_id',
            'is_ritual_only',
        ]);
    }

    /**
     * Get the EntitySpell pivot records for this entity.
     *
     * Use this when you need access to the pivot model directly,
     * such as for spell choices or complex pivot data queries.
     */
    public function entitySpellRecords(): MorphMany
    {
        return $this->morphMany(EntitySpell::class, 'reference', 'reference_type', 'reference_id');
    }
}
