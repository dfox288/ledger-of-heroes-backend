<?php

namespace App\Models;

use App\Enums\OptionalFeatureType;
use App\Enums\ResourceType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Laravel\Scout\Searchable;
use Spatie\Tags\HasTags;

class OptionalFeature extends BaseModel
{
    use HasTags, Searchable;

    protected $fillable = [
        'slug',
        'name',
        'feature_type',
        'level_requirement',
        'prerequisite_text',
        'description',
        'casting_time',
        'range',
        'duration',
        'spell_school_id',
        'resource_type',
        'resource_cost',
    ];

    protected $casts = [
        'feature_type' => OptionalFeatureType::class,
        'resource_type' => ResourceType::class,
        'level_requirement' => 'integer',
        'resource_cost' => 'integer',
        'spell_school_id' => 'integer',
    ];

    // Relationships

    /**
     * Get the spell school for spell-like features (Elemental Disciplines, etc.)
     */
    public function spellSchool(): BelongsTo
    {
        return $this->belongsTo(SpellSchool::class);
    }

    /**
     * Get the classes that can use this optional feature.
     * Uses Laravel's alphabetical pivot table naming: class_optional_feature
     */
    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(CharacterClass::class, 'class_optional_feature', 'optional_feature_id', 'class_id')
            ->withPivot('subclass_name')
            ->withTimestamps();
    }

    /**
     * Get the pivot records for class associations.
     * Useful for accessing subclass_name directly.
     */
    public function classPivots(): HasMany
    {
        return $this->hasMany(ClassOptionalFeature::class);
    }

    /**
     * Get damage/effect rolls for this feature.
     */
    public function rolls(): MorphMany
    {
        return $this->morphMany(EntityDataTable::class, 'reference');
    }

    /**
     * Get source citations for this feature.
     */
    public function sources(): MorphMany
    {
        return $this->morphMany(EntitySource::class, 'reference');
    }

    /**
     * Get structured prerequisites for this feature.
     */
    public function prerequisites(): MorphMany
    {
        return $this->morphMany(EntityPrerequisite::class, 'reference');
    }

    // Scopes

    /**
     * Filter by feature type.
     */
    public function scopeOfType($query, OptionalFeatureType|string $type)
    {
        $value = $type instanceof OptionalFeatureType ? $type->value : $type;

        return $query->where('feature_type', $value);
    }

    /**
     * Filter by class association.
     */
    public function scopeForClass($query, int|CharacterClass $class)
    {
        $classId = $class instanceof CharacterClass ? $class->id : $class;

        return $query->whereHas('classes', fn ($q) => $q->where('classes.id', $classId));
    }

    /**
     * Filter by subclass name.
     */
    public function scopeForSubclass($query, string $subclassName)
    {
        return $query->whereHas('classPivots', fn ($q) => $q->where('subclass_name', $subclassName));
    }

    // Computed Attributes

    /**
     * Check if this feature has spell-like mechanics (casting time, range, etc.)
     */
    public function getHasSpellMechanicsAttribute(): bool
    {
        return $this->casting_time !== null || $this->range !== null;
    }

    // Scout Searchable

    public function toSearchableArray(): array
    {
        $this->loadMissing(['classes', 'classPivots', 'sources.source', 'tags', 'spellSchool']);

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'feature_type' => $this->feature_type?->value,
            'level_requirement' => $this->level_requirement,
            'prerequisite_text' => $this->prerequisite_text,
            'description' => $this->description,
            'resource_type' => $this->resource_type?->value,
            'resource_cost' => $this->resource_cost,
            'has_spell_mechanics' => $this->has_spell_mechanics,
            'class_slugs' => $this->classes->pluck('slug')->unique()->values()->all(),
            'class_names' => $this->classes->pluck('name')->unique()->values()->all(),
            'subclass_names' => $this->classPivots->pluck('subclass_name')->filter()->unique()->values()->all(),
            'source_codes' => $this->sources->pluck('source.code')->unique()->values()->all(),
            'tag_slugs' => $this->tags->pluck('slug')->all(),
        ];
    }

    public function searchableWith(): array
    {
        return ['classes', 'classPivots', 'sources.source', 'tags'];
    }

    public function searchableAs(): string
    {
        return config('scout.prefix').'optional_features';
    }

    /**
     * Get the Meilisearch settings for this model's index.
     */
    public function searchableOptions(): array
    {
        return [
            'filterableAttributes' => [
                'id',
                'slug',
                'feature_type',
                'level_requirement',
                'resource_type',
                'resource_cost',
                'has_spell_mechanics',
                'class_slugs',
                'subclass_names',
                'source_codes',
                'tag_slugs',
            ],
            'sortableAttributes' => [
                'name',
                'level_requirement',
                'resource_cost',
            ],
            'searchableAttributes' => [
                'name',
                'description',
                'prerequisite_text',
                'class_names',
                'subclass_names',
            ],
        ];
    }
}
