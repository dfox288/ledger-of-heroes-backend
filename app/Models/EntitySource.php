<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EntitySource extends BaseModel
{
    protected $fillable = [
        'reference_type',
        'reference_id',
        'source_id',
        'pages',
    ];

    protected $casts = [
        'reference_id' => 'integer',
        'source_id' => 'integer',
    ];

    // Relationship to sources table
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    // Polymorphic relationship to any entity
    // Note: Laravel uses 'reference' as the relationship name for consistency with other polymorphic tables
    public function reference(): MorphTo
    {
        return $this->morphTo('reference', 'reference_type', 'reference_id');
    }
}
