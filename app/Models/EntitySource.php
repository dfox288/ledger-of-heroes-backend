<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EntitySource extends Model
{
    public $timestamps = false; // CRITICAL: No timestamps on static data

    protected $fillable = [
        'entity_type',
        'entity_id',
        'source_id',
        'pages',
    ];

    protected $casts = [
        'entity_id' => 'integer',
        'source_id' => 'integer',
    ];

    // Relationship to sources table
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    // Polymorphic relationship to any entity
    // Note: Laravel expects 'entity' method name for 'entity_type'/'entity_id' columns
    public function entity(): MorphTo
    {
        return $this->morphTo('entity', 'entity_type', 'entity_id');
    }
}
