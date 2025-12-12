<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * EntityItem - Polymorphic pivot for fixed starting equipment grants.
 *
 * Table: entity_items
 * Used by: CharacterClass, Background
 *
 * Represents fixed equipment granted by a class or background.
 * Equipment choices are stored in entity_choices table.
 */
class EntityItem extends BaseModel
{
    protected $fillable = [
        'reference_type',
        'reference_id',
        'item_id',
        'quantity',
        'description',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function reference(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'reference_type', 'reference_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
