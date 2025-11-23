<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Laravel\Scout\Searchable;
use Spatie\Tags\HasTags;

class Monster extends BaseModel
{
    use HasTags, Searchable;

    protected $fillable = [
        'name',
        'slug',
        'size_id',
        'type',
        'alignment',
        'armor_class',
        'armor_type',
        'hit_points_average',
        'hit_dice',
        'speed_walk',
        'speed_fly',
        'speed_swim',
        'speed_burrow',
        'speed_climb',
        'can_hover',
        'strength',
        'dexterity',
        'constitution',
        'intelligence',
        'wisdom',
        'charisma',
        'challenge_rating',
        'experience_points',
        'description',
    ];

    protected $casts = [
        'can_hover' => 'boolean',
    ];

    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class);
    }

    public function traits(): HasMany
    {
        return $this->hasMany(MonsterTrait::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(MonsterAction::class);
    }

    public function legendaryActions(): HasMany
    {
        return $this->hasMany(MonsterLegendaryAction::class);
    }

    public function spellcasting(): HasOne
    {
        return $this->hasOne(MonsterSpellcasting::class);
    }

    public function spells(): MorphToMany
    {
        return $this->morphToMany(
            Spell::class,
            'entity',
            'monster_spells',
            'monster_id',
            'spell_id'
        )->withPivot('usage_type', 'usage_limit');
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

    public function modifiers(): MorphMany
    {
        return $this->morphMany(Modifier::class, 'reference');
    }

    public function conditions(): MorphToMany
    {
        return $this->morphToMany(Condition::class, 'reference', 'entity_conditions')
            ->withPivot('description');
    }

    public function sources(): MorphMany
    {
        return $this->morphMany(EntitySource::class, 'reference');
    }

    // Scout Search Configuration

    /**
     * Get the indexable data array for the model.
     */
    public function toSearchableArray(): array
    {
        // Load relationships to avoid N+1 queries
        $this->loadMissing(['size', 'sources.source', 'entitySpells']);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'size_code' => $this->size?->code,
            'size_name' => $this->size?->name,
            'type' => $this->type,
            'alignment' => $this->alignment,
            'armor_class' => $this->armor_class,
            'armor_type' => $this->armor_type,
            'hit_points_average' => $this->hit_points_average,
            'challenge_rating' => $this->challenge_rating,
            'experience_points' => $this->experience_points,
            'description' => $this->description,
            'sources' => $this->sources->pluck('source.name')->all(),
            'source_codes' => $this->sources->pluck('source.code')->all(),
            // Spell slugs for fast Meilisearch filtering (1,098 relationships for 129 spellcasters)
            'spell_slugs' => $this->entitySpells->pluck('slug')->all(),
        ];
    }

    /**
     * Get the name of the index associated with the model.
     */
    public function searchableAs(): string
    {
        return 'monsters_index';
    }
}
