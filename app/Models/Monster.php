<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Monster extends Model
{
    use HasFactory;

    public $timestamps = false;

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

    public function modifiers(): MorphMany
    {
        return $this->morphMany(Modifier::class, 'entity');
    }

    public function conditions(): MorphToMany
    {
        return $this->morphToMany(Condition::class, 'entity', 'entity_conditions')
            ->withPivot('description');
    }

    public function sources(): MorphToMany
    {
        return $this->morphToMany(Source::class, 'entity', 'entity_sources')
            ->withPivot('source_pages');
    }
}
