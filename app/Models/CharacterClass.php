<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Laravel\Scout\Searchable;
use Spatie\Tags\HasTags;

class CharacterClass extends Model
{
    use HasFactory, HasTags, Searchable;

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

    /**
     * Scope: Filter by granted proficiency name
     * Usage: CharacterClass::grantsProficiency('longsword')->get()
     */
    public function scopeGrantsProficiency($query, string $proficiencyName)
    {
        return $query->whereHas('proficiencies', function ($q) use ($proficiencyName) {
            $q->where('proficiency_name', 'LIKE', "%{$proficiencyName}%")
                ->orWhereHas('proficiencyType', function ($typeQuery) use ($proficiencyName) {
                    $typeQuery->where('name', 'LIKE', "%{$proficiencyName}%");
                });
        });
    }

    /**
     * Scope: Filter by granted skill proficiency
     * Usage: CharacterClass::grantsSkill('insight')->get()
     */
    public function scopeGrantsSkill($query, string $skillName)
    {
        return $query->whereHas('proficiencies', function ($q) use ($skillName) {
            $q->where('proficiency_type', 'skill')
                ->whereHas('skill', function ($skillQuery) use ($skillName) {
                    $skillQuery->where('name', 'LIKE', "%{$skillName}%");
                });
        });
    }

    /**
     * Scope: Filter by proficiency type category
     * Usage: CharacterClass::grantsProficiencyType('martial')->get()
     */
    public function scopeGrantsProficiencyType($query, string $categoryOrName)
    {
        return $query->whereHas('proficiencies', function ($q) use ($categoryOrName) {
            $q->whereHas('proficiencyType', function ($typeQuery) use ($categoryOrName) {
                $typeQuery->where('category', 'LIKE', "%{$categoryOrName}%")
                    ->orWhere('name', 'LIKE', "%{$categoryOrName}%");
            });
        });
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
