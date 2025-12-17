<?php

namespace App\Models\Concerns;

use App\Models\EntityChoice;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Trait for models that can have entity choices.
 *
 * Provides relationships to query choices from the unified entity_choices table.
 */
trait HasEntityChoices
{
    /**
     * Get all choices granted by this entity.
     */
    public function choices(): MorphMany
    {
        return $this->morphMany(EntityChoice::class, 'reference');
    }

    /**
     * Get language choices.
     */
    public function languageChoices(): MorphMany
    {
        return $this->choices()->ofType('language');
    }

    /**
     * Get spell choices.
     */
    public function spellChoices(): MorphMany
    {
        return $this->choices()->ofType('spell');
    }

    /**
     * Get proficiency choices.
     */
    public function proficiencyChoices(): MorphMany
    {
        return $this->choices()->ofType('proficiency');
    }

    /**
     * Get ability score choices.
     */
    public function abilityScoreChoices(): MorphMany
    {
        return $this->choices()->ofType('ability_score');
    }

    /**
     * Get equipment choices.
     */
    public function equipmentChoices(): MorphMany
    {
        return $this->choices()->ofType('equipment');
    }

    /**
     * Get all non-equipment choices (for the `choices` API field).
     *
     * Returns language, spell, proficiency, and ability_score choices.
     * Equipment choices are handled separately via equipmentChoices().
     */
    public function nonEquipmentChoices(): MorphMany
    {
        return $this->choices()->whereIn('choice_type', [
            'ability_score',
            'language',
            'proficiency',
            'spell',
        ]);
    }
}
