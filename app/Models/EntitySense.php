<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Polymorphic pivot model for entity senses.
 *
 * Links Monster/Race to Sense with range and limitation details.
 */
class EntitySense extends BaseModel
{
    protected $fillable = [
        'reference_type',
        'reference_id',
        'sense_id',
        'range_feet',
        'is_limited',
        'notes',
    ];

    protected $casts = [
        'reference_id' => 'integer',
        'sense_id' => 'integer',
        'range_feet' => 'integer',
        'is_limited' => 'boolean',
    ];

    /**
     * The entity this sense belongs to (Monster, Race).
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The sense type (darkvision, blindsight, etc.).
     */
    public function sense(): BelongsTo
    {
        return $this->belongsTo(Sense::class);
    }
}
