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
 * Relationships provided:
 * - spells(): Returns fixed Spell models (via entity_spells table)
 * - entitySpellRecords(): Returns EntitySpell pivot records for fixed spells
 *
 * Note: For spell choices, use the spellChoices() method from HasEntityChoices trait.
 */
trait HasEntitySpells
{
    /**
     * Get fixed spells granted by this entity.
     *
     * Returns Spell models directly via the polymorphic entity_spells pivot table.
     * Only returns fixed spells (not spell choices).
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
            'charges_cost_min',
            'charges_cost_max',
            'charges_cost_formula',
        ]);
    }

    /**
     * Get the EntitySpell pivot records for this entity.
     *
     * Use this when you need access to the pivot model directly.
     */
    public function entitySpellRecords(): MorphMany
    {
        return $this->morphMany(EntitySpell::class, 'reference', 'reference_type', 'reference_id');
    }
}
