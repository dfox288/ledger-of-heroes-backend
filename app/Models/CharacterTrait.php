<?php

namespace App\Models;

use App\Enums\ResetTiming;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Character traits from races, classes, and backgrounds.
 *
 * Traits can have limited uses (e.g., Dragonborn Breath Weapon) tracked via:
 * - resets_on: When the trait recharges (short_rest, long_rest, dawn)
 * - counters(): Associated counter records for level-based progression
 */
class CharacterTrait extends BaseModel
{
    protected $table = 'entity_traits';

    protected $fillable = [
        'reference_type',
        'reference_id',
        'name',
        'category',
        'description',
        'attack_data',
        'sort_order',
        'resets_on',
    ];

    protected $casts = [
        'reference_id' => 'integer',
        'sort_order' => 'integer',
        'resets_on' => ResetTiming::class,
    ];

    // Polymorphic relationship to parent entity (Race, Class, etc.)
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    // Data tables that reference this trait (via reference_type/reference_id)
    public function dataTables(): MorphMany
    {
        return $this->morphMany(EntityDataTable::class, 'reference');
    }

    /**
     * Get the counters (usage limits) for this trait.
     *
     * Traits with limited uses (like Breath Weapon) have counter records
     * storing the base uses and potentially level-based progression.
     */
    public function counters(): MorphMany
    {
        return $this->morphMany(EntityCounter::class, 'reference');
    }
}
