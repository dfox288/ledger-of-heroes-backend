<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ClassFeature extends BaseModel
{
    protected $table = 'class_features';

    protected $fillable = [
        'class_id',
        'level',
        'feature_name',
        'is_optional',
        'description',
        'sort_order',
    ];

    protected $casts = [
        'class_id' => 'integer',
        'level' => 'integer',
        'is_optional' => 'boolean',
        'sort_order' => 'integer',
    ];

    // Relationships
    public function characterClass(): BelongsTo
    {
        return $this->belongsTo(CharacterClass::class, 'class_id');
    }

    /**
     * Random tables and reference tables associated with this feature.
     * Includes <roll> elements and pipe-delimited tables from feature text.
     */
    public function randomTables(): MorphMany
    {
        return $this->morphMany(
            RandomTable::class,
            'reference',
            'reference_type',
            'reference_id'
        );
    }

    /**
     * Special tags for this class feature (fighting styles, unarmored defense, etc.).
     */
    public function specialTags(): HasMany
    {
        return $this->hasMany(ClassFeatureSpecialTag::class);
    }
}
