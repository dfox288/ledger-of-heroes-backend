<?php

namespace App\Models;

use App\Models\Concerns\HasLanguageScopes;
use App\Models\Concerns\HasProficiencyScopes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Laravel\Scout\Searchable;
use Spatie\Tags\HasTags;

class Race extends BaseModel
{
    use HasLanguageScopes, HasProficiencyScopes, HasTags, Searchable;

    protected $fillable = [
        'slug',
        'name',
        'size_id',
        'speed',
        'parent_race_id',
    ];

    protected $casts = [
        'size_id' => 'integer',
        'speed' => 'integer',
        'parent_race_id' => 'integer',
    ];

    // Relationships
    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Race::class, 'parent_race_id');
    }

    public function subraces(): HasMany
    {
        return $this->hasMany(Race::class, 'parent_race_id');
    }

    public function proficiencies(): MorphMany
    {
        return $this->morphMany(Proficiency::class, 'reference');
    }

    public function traits(): MorphMany
    {
        return $this->morphMany(CharacterTrait::class, 'reference');
    }

    public function modifiers(): MorphMany
    {
        return $this->morphMany(Modifier::class, 'reference');
    }

    public function sources(): MorphMany
    {
        return $this->morphMany(EntitySource::class, 'reference', 'reference_type', 'reference_id');
    }

    public function languages(): MorphMany
    {
        return $this->morphMany(EntityLanguage::class, 'reference');
    }

    public function conditions(): MorphMany
    {
        return $this->morphMany(EntityCondition::class, 'reference', 'reference_type', 'reference_id');
    }

    public function spells(): MorphMany
    {
        return $this->morphMany(EntitySpell::class, 'reference', 'reference_type', 'reference_id');
    }

    public function entitySpells(): MorphToMany
    {
        return $this->morphToMany(
            Spell::class,
            'reference',
            'entity_spells',
            'reference_id',
            'spell_id'
        )->withPivot([
            'ability_score_id',
            'level_requirement',
            'usage_limit',
            'is_cantrip',
        ]);
    }

    // Scopes for API filtering
    public function scopeSearch($query, $searchTerm)
    {
        // Search name only (learned from spells - don't search description)
        return $query->where('name', 'LIKE', "%{$searchTerm}%");
    }

    public function scopeSize($query, $sizeId)
    {
        return $query->where('size_id', $sizeId);
    }

    // Scout Searchable Methods
    public function toSearchableArray(): array
    {
        // Load tags relationship if not already loaded
        $this->loadMissing(['tags']);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'size_name' => $this->size?->name,
            'size_code' => $this->size?->code,
            'speed' => $this->speed,
            'sources' => $this->sources->pluck('source.name')->unique()->values()->all(),
            'source_codes' => $this->sources->pluck('source.code')->unique()->values()->all(),
            'is_subrace' => $this->parent_race_id !== null,
            'parent_race_name' => $this->parent?->name,
            // Tag slugs for filtering (e.g., darkvision, fey_ancestry)
            'tag_slugs' => $this->tags->pluck('slug')->all(),
        ];
    }

    public function searchableWith(): array
    {
        return ['size', 'sources.source', 'parent', 'tags'];
    }

    public function searchableAs(): string
    {
        $prefix = config('scout.prefix');

        return $prefix.'races';
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
                'size_name',
                'size_code',
                'speed',
                'source_codes',
                'is_subrace',
                'parent_race_name',
                'tag_slugs',
            ],
            'sortableAttributes' => [
                'name',
                'speed',
            ],
            'searchableAttributes' => [
                'name',
                'size_name',
                'parent_race_name',
                'sources',
            ],
        ];
    }
}
