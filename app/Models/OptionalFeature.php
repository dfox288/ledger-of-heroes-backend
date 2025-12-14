<?php

namespace App\Models;

use App\Enums\ActionCost;
use App\Enums\OptionalFeatureType;
use App\Enums\ResourceType;
use App\Models\Concerns\HasPrerequisites;
use App\Models\Concerns\HasSearchableHelpers;
use App\Models\Concerns\HasSources;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Laravel\Scout\Searchable;
use Spatie\Tags\HasTags;

class OptionalFeature extends BaseModel
{
    use HasPrerequisites, HasSearchableHelpers, HasSources, HasTags, Searchable;

    protected $fillable = [
        'slug',
        'name',
        'feature_type',
        'level_requirement',
        'prerequisite_text',
        'description',
        'casting_time',
        'action_cost',
        'range',
        'duration',
        'spell_school_id',
        'resource_type',
        'resource_cost',
    ];

    protected $casts = [
        'feature_type' => OptionalFeatureType::class,
        'action_cost' => ActionCost::class,
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
        $this->loadMissing(['classes.parentClass', 'classPivots', 'sources.source', 'tags', 'spellSchool']);

        // Collect class slugs including parent class slugs for subclasses
        $classSlugs = collect();
        $classNames = collect();
        $subclassNames = collect();

        foreach ($this->classes as $class) {
            // Add this class's slug and name
            $classSlugs->push($class->slug);
            $classNames->push($class->name);

            // If this is a subclass, also add parent class slug and derive subclass name
            if ($class->parent_class_id !== null) {
                if ($class->parentClass) {
                    $classSlugs->push($class->parentClass->slug);
                    $classNames->push($class->parentClass->name);
                }
                // This class IS a subclass, so its name is the subclass name
                $subclassNames->push($class->name);
            }
        }

        // Also include subclass names from pivot table (fallback when subclass entity doesn't exist)
        foreach ($this->classPivots as $pivot) {
            if ($pivot->subclass_name) {
                $subclassNames->push($pivot->subclass_name);
            }
        }

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
            'class_slugs' => $classSlugs->unique()->values()->all(),
            'class_names' => $classNames->unique()->values()->all(),
            'subclass_names' => $subclassNames->filter()->unique()->values()->all(),
            'sources' => $this->getSearchableSourceNames(),
            'source_codes' => $this->getSearchableSourceCodes(),
            'tag_slugs' => $this->getSearchableTagSlugs(),
        ];
    }

    public function searchableWith(): array
    {
        return ['classes.parentClass', 'classPivots', 'sources.source', 'tags'];
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
