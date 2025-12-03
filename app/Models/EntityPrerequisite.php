<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * EntityPrerequisite - Polymorphic pivot for entity prerequisites.
 *
 * Table: entity_prerequisites
 * Used by: Feat, Item, OptionalFeature
 *
 * Represents requirements to use/take an entity (ability score minimums, race requirements, etc.).
 * Uses double polymorphic pattern: reference → entity that HAS prerequisite, prerequisite → the requirement.
 */
class EntityPrerequisite extends BaseModel
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'reference_type',
        'reference_id',
        'prerequisite_type',
        'prerequisite_id',
        'minimum_value',
        'description',
        'group_id',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'minimum_value' => 'integer',
        'group_id' => 'integer',
    ];

    /**
     * Get the entity that HAS this prerequisite (Feat, Item, Class, etc.).
     */
    public function reference(): MorphTo
    {
        return $this->morphTo('reference');
    }

    /**
     * Get the entity that IS the prerequisite (AbilityScore, Race, ProficiencyType, etc.).
     */
    public function prerequisite(): MorphTo
    {
        return $this->morphTo('prerequisite');
    }
}
