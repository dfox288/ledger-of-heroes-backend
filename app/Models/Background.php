<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Laravel\Scout\Searchable;
use Spatie\Tags\HasTags;

class Background extends Model
{
    use HasFactory, HasTags, Searchable;

    public $timestamps = false;

    protected $fillable = [
        'slug',
        'name',
    ];

    // Polymorphic relationships
    public function traits(): MorphMany
    {
        return $this->morphMany(CharacterTrait::class, 'reference');
    }

    public function proficiencies(): MorphMany
    {
        return $this->morphMany(Proficiency::class, 'reference');
    }

    public function sources(): MorphMany
    {
        return $this->morphMany(EntitySource::class, 'reference', 'reference_type', 'reference_id');
    }

    public function equipment(): MorphMany
    {
        return $this->morphMany(EntityItem::class, 'reference');
    }

    public function languages(): MorphMany
    {
        return $this->morphMany(EntityLanguage::class, 'reference');
    }

    // Scopes for API filtering
    public function scopeSearch($query, $searchTerm)
    {
        return $query->where('name', 'LIKE', "%{$searchTerm}%")
            ->orWhereHas('traits', fn ($q) => $q->where('text', 'LIKE', "%{$searchTerm}%"));
    }

    /**
     * Scope: Filter by granted proficiency name
     * Usage: Background::grantsProficiency('longsword')->get()
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
     * Usage: Background::grantsSkill('insight')->get()
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
     * Usage: Background::grantsProficiencyType('martial')->get()
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
     * Usage: Background::speaksLanguage('elvish')->get()
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
     * Usage: Background::languageChoiceCount(2)->get()
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
     * Usage: Background::grantsLanguages()->get()
     */
    public function scopeGrantsLanguages($query)
    {
        return $query->has('languages');
    }

    // Scout Searchable Methods
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sources' => $this->sources->pluck('source.name')->unique()->values()->all(),
            'source_codes' => $this->sources->pluck('source.code')->unique()->values()->all(),
        ];
    }

    public function searchableWith(): array
    {
        return ['sources.source'];
    }

    public function searchableAs(): string
    {
        return 'backgrounds';
    }
}
