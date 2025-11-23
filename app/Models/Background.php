<?php

namespace App\Models;

use App\Models\Concerns\HasLanguageScopes;
use App\Models\Concerns\HasProficiencyScopes;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Laravel\Scout\Searchable;
use Spatie\Tags\HasTags;

class Background extends BaseModel
{
    use HasLanguageScopes, HasProficiencyScopes, HasTags, Searchable;

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
