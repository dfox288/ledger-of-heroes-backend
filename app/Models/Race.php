<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Race extends Model
{
    use HasFactory;

    public $timestamps = false;

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

    /**
     * Scope: Filter by granted proficiency name
     * Usage: Race::grantsProficiency('longsword')->get()
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
     * Usage: Race::grantsSkill('insight')->get()
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
     * Usage: Race::grantsProficiencyType('martial')->get()
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

    /**
     * Scope: Filter by spoken language
     * Usage: Race::speaksLanguage('elvish')->get()
     */
    public function scopeSpeaksLanguage($query, string $languageName)
    {
        return $query->whereHas('languages', function ($q) use ($languageName) {
            $q->where('is_choice', false)
                ->whereHas('language', function ($langQuery) use ($languageName) {
                    $langQuery->where('name', 'LIKE', "%{$languageName}%");
                });
        });
    }

    /**
     * Scope: Filter by language choice count
     * Usage: Race::languageChoiceCount(2)->get()
     * Note: Counts the number of choice slots (is_choice=true records)
     */
    public function scopeLanguageChoiceCount($query, int $count)
    {
        return $query->whereHas('languages', function ($q) {
            $q->where('is_choice', true);
        }, '=', $count);
    }

    /**
     * Scope: Filter entities that grant any languages
     * Usage: Race::grantsLanguages()->get()
     */
    public function scopeGrantsLanguages($query)
    {
        return $query->has('languages');
    }
}
