<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * EntityLanguage - Polymorphic pivot for fixed language grants.
 *
 * Table: entity_languages
 * Used by: Background, Race, Feat
 *
 * Represents fixed languages granted by races or backgrounds.
 * Choice-based language grants are stored in entity_choices table.
 */
class EntityLanguage extends BaseModel
{
    protected $fillable = [
        'reference_type',
        'reference_id',
        'language_id',
    ];

    protected $casts = [
        'reference_id' => 'integer',
        'language_id' => 'integer',
    ];

    // Polymorphic relationship to parent entity (Race, Background, Class, etc.)
    public function reference(): MorphTo
    {
        return $this->morphTo(null, 'reference_type', 'reference_id');
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }
}
