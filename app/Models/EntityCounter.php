<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Polymorphic counter storage for limited-use features.
 *
 * Supports counters for:
 * - CharacterClass (e.g., Ki Points, Sorcery Points, Rage uses)
 * - Feat (e.g., Inspiring Leader uses)
 * - CharacterTrait (e.g., Breath Weapon uses)
 */
class EntityCounter extends BaseModel
{
    protected $table = 'entity_counters';

    protected $fillable = [
        'reference_type',
        'reference_id',
        'level',
        'counter_name',
        'counter_value',
        'reset_timing',
    ];

    protected $casts = [
        'reference_id' => 'integer',
        'level' => 'integer',
        'counter_value' => 'integer',
    ];

    // Polymorphic relationship to parent entity (CharacterClass, Feat, CharacterTrait)
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
