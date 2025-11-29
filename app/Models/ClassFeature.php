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
        'is_multiclass_only',
        'parent_feature_id',
        'description',
        'sort_order',
    ];

    protected $casts = [
        'class_id' => 'integer',
        'level' => 'integer',
        'is_optional' => 'boolean',
        'is_multiclass_only' => 'boolean',
        'parent_feature_id' => 'integer',
        'sort_order' => 'integer',
    ];

    // Relationships
    public function characterClass(): BelongsTo
    {
        return $this->belongsTo(CharacterClass::class, 'class_id');
    }

    /**
     * Get the parent feature if this is a choice option.
     *
     * Example: "Fighting Style: Archery" has parent "Fighting Style".
     */
    public function parentFeature(): BelongsTo
    {
        return $this->belongsTo(ClassFeature::class, 'parent_feature_id');
    }

    /**
     * Get child features (choice options) under this parent feature.
     *
     * Example: "Fighting Style" has children "Fighting Style: Archery", etc.
     */
    public function childFeatures(): HasMany
    {
        return $this->hasMany(ClassFeature::class, 'parent_feature_id');
    }

    /**
     * Data tables associated with this feature.
     * Includes random rolls, damage tables, and reference tables from feature text.
     */
    public function dataTables(): MorphMany
    {
        return $this->morphMany(
            EntityDataTable::class,
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

    // Accessors

    /**
     * Check if this feature is a choice option (has a parent feature).
     *
     * D&D Context: Choice options like "Fighting Style: Archery" are picked
     * from a list, rather than being gained automatically.
     */
    public function getIsChoiceOptionAttribute(): bool
    {
        return $this->parent_feature_id !== null;
    }

    // Helper Methods

    /**
     * Check if this feature has child features (choice options).
     */
    public function hasChildren(): bool
    {
        return $this->childFeatures()->exists();
    }

    // Scopes

    /**
     * Scope to only top-level features (exclude child/choice options).
     *
     * Use this to get the main feature list without nested options.
     */
    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_feature_id');
    }
}
