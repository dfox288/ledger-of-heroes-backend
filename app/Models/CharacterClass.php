<?php

namespace App\Models;

use App\Models\Concerns\HasProficiencyScopes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Laravel\Scout\Searchable;
use Spatie\Tags\HasTags;

class CharacterClass extends BaseModel
{
    use HasProficiencyScopes, HasTags, Searchable;

    protected $table = 'classes';

    protected $fillable = [
        'slug',
        'name',
        'parent_class_id',
        'hit_die',
        'description',
        'primary_ability',
        'spellcasting_ability_id',
    ];

    protected $casts = [
        'hit_die' => 'integer',
        'parent_class_id' => 'integer',
        'spellcasting_ability_id' => 'integer',
    ];

    // Relationships
    public function spellcastingAbility(): BelongsTo
    {
        return $this->belongsTo(AbilityScore::class, 'spellcasting_ability_id');
    }

    public function parentClass(): BelongsTo
    {
        return $this->belongsTo(CharacterClass::class, 'parent_class_id');
    }

    public function subclasses(): HasMany
    {
        return $this->hasMany(CharacterClass::class, 'parent_class_id');
    }

    public function features(): HasMany
    {
        return $this->hasMany(ClassFeature::class, 'class_id');
    }

    public function levelProgression(): HasMany
    {
        return $this->hasMany(ClassLevelProgression::class, 'class_id');
    }

    public function counters(): HasMany
    {
        return $this->hasMany(ClassCounter::class, 'class_id');
    }

    public function proficiencies(): MorphMany
    {
        return $this->morphMany(Proficiency::class, 'reference');
    }

    public function traits(): MorphMany
    {
        return $this->morphMany(CharacterTrait::class, 'reference');
    }

    public function spells(): BelongsToMany
    {
        return $this->belongsToMany(Spell::class, 'class_spells', 'class_id', 'spell_id');
    }

    public function sources(): MorphMany
    {
        return $this->morphMany(EntitySource::class, 'reference', 'reference_type', 'reference_id');
    }

    public function equipment(): MorphMany
    {
        return $this->morphMany(EntityItem::class, 'reference');
    }

    public function modifiers(): MorphMany
    {
        return $this->morphMany(Modifier::class, 'reference');
    }

    // Computed property
    public function getIsBaseClassAttribute(): bool
    {
        return is_null($this->parent_class_id);
    }

    /**
     * Get all features including inherited base class features.
     *
     * For subclasses, merges parent class features with subclass-specific features.
     * Features are sorted by level, then by sort_order to maintain proper ordering.
     *
     * @param  bool  $includeInherited  Whether to include parent class features (default: true)
     * @return \Illuminate\Support\Collection
     */
    public function getAllFeatures(bool $includeInherited = true)
    {
        // Base classes or when inheritance disabled: return only this class's features
        if (! $includeInherited || $this->parent_class_id === null) {
            return $this->features->sortBy([
                ['level', 'asc'],
                ['sort_order', 'asc'],
            ])->values();
        }

        // Subclasses: merge parent + subclass features
        // Only if parent relationship and its features are loaded
        if ($this->relationLoaded('parentClass') && $this->parentClass->relationLoaded('features')) {
            return $this->parentClass->features
                ->concat($this->features)
                ->sortBy([
                    ['level', 'asc'],
                    ['sort_order', 'asc'],
                ])
                ->values();
        }

        // Fallback: If parent features not loaded, return only subclass features
        return $this->features->sortBy([
            ['level', 'asc'],
            ['sort_order', 'asc'],
        ])->values();
    }

    // Scout Searchable Methods
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'hit_die' => $this->hit_die,
            'description' => $this->description,
            'primary_ability' => $this->primary_ability,
            'spellcasting_ability' => $this->spellcastingAbility?->code,
            'sources' => $this->sources->pluck('source.name')->unique()->values()->all(),
            'source_codes' => $this->sources->pluck('source.code')->unique()->values()->all(),
            'is_subclass' => $this->parent_class_id !== null,
            'parent_class_name' => $this->parentClass?->name,
        ];
    }

    public function searchableWith(): array
    {
        return ['sources.source', 'parentClass', 'spellcastingAbility'];
    }

    public function searchableAs(): string
    {
        return 'classes';
    }
}
