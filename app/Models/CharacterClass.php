<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class CharacterClass extends Model
{
    use HasFactory;

    public $timestamps = false;

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

    // Computed property
    public function getIsBaseClassAttribute(): bool
    {
        return is_null($this->parent_class_id);
    }
}
