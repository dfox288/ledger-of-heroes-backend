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
        // Load relationships if not already loaded
        $this->loadMissing(['tags', 'proficiencies.skill', 'proficiencies.proficiencyType', 'spells']);

        // Calculate max spell level
        $maxSpellLevel = $this->spells->max('level');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'hit_die' => $this->hit_die,
            'description' => $this->description,
            'primary_ability' => $this->primary_ability,
            'spellcasting_ability' => $this->spellcastingAbility?->code,
            'is_spellcaster' => $this->spellcasting_ability_id !== null,
            'sources' => $this->sources->pluck('source.name')->unique()->values()->all(),
            'source_codes' => $this->sources->pluck('source.code')->unique()->values()->all(),
            'is_subclass' => $this->parent_class_id !== null,
            'is_base_class' => $this->parent_class_id === null,
            'parent_class_name' => $this->parentClass?->name,
            // Tag slugs for filtering (e.g., spellcaster, martial, half_caster)
            'tag_slugs' => $this->tags->pluck('slug')->all(),
            // Phase 3: Spell counts (quick wins)
            'has_spells' => $this->spells_count > 0,
            'spell_count' => $this->spells_count,
            'max_spell_level' => $maxSpellLevel !== null ? (int) $maxSpellLevel : null,
            // Phase 4: Proficiencies (high value filtering)
            'saving_throw_proficiencies' => $this->proficiencies
                ->where('proficiency_type', 'saving_throw')
                ->pluck('proficiency_name')
                ->values()->all(),
            'armor_proficiencies' => $this->proficiencies
                ->where('proficiency_type', 'armor')
                ->pluck('proficiency_name')
                ->values()->all(),
            'weapon_proficiencies' => $this->proficiencies
                ->where('proficiency_type', 'weapon')
                ->pluck('proficiency_name')
                ->values()->all(),
            'tool_proficiencies' => $this->proficiencies
                ->where('proficiency_type', 'tool')
                ->pluck('proficiency_name')
                ->values()->all(),
            'skill_proficiencies' => $this->proficiencies
                ->where('proficiency_type', 'skill')
                ->pluck('proficiency_name')
                ->values()->all(),
        ];
    }

    public function searchableWith(): array
    {
        return [
            'sources.source',
            'parentClass',
            'spellcastingAbility',
            'tags',
            'proficiencies.skill',
            'proficiencies.proficiencyType',
            'spells',
        ];
    }

    public function searchableWithCount(): array
    {
        return ['spells'];
    }

    public function searchableAs(): string
    {
        $prefix = config('scout.prefix');

        return $prefix.'classes';
    }

    /**
     * Get the Meilisearch settings for this model's index.
     *
     * Used by `php artisan scout:sync-index-settings`.
     */
    public function searchableOptions(): array
    {
        return [
            'filterableAttributes' => [
                'id',
                'slug',
                'hit_die',
                'primary_ability',
                'spellcasting_ability',
                'is_spellcaster',
                'source_codes',
                'is_subclass',
                'is_base_class',
                'parent_class_name',
                'tag_slugs',
                // Phase 3: Spell counts
                'has_spells',
                'spell_count',
                'max_spell_level',
                // Phase 4: Proficiencies
                'saving_throw_proficiencies',
                'armor_proficiencies',
                'weapon_proficiencies',
                'tool_proficiencies',
                'skill_proficiencies',
            ],
            'sortableAttributes' => [
                'name',
                'hit_die',
                'spell_count',
            ],
            'searchableAttributes' => [
                'name',
                'description',
                'primary_ability',
                'spellcasting_ability',
                'parent_class_name',
                'sources',
                'saving_throw_proficiencies',
                'armor_proficiencies',
                'weapon_proficiencies',
                'tool_proficiencies',
                'skill_proficiencies',
            ],
        ];
    }
}
