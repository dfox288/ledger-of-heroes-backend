<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Language extends BaseModel
{
    protected $fillable = [
        'name',
        'slug',
        'script',
        'typical_speakers',
        'description',
    ];

    public function entityLanguages(): HasMany
    {
        return $this->hasMany(EntityLanguage::class);
    }

    /**
     * Get all races that speak this language
     */
    public function races(): MorphToMany
    {
        return $this->morphedByMany(
            Race::class,
            'reference',
            'entity_languages',
            'language_id',
            'reference_id'
        )
            ->withPivot('is_choice')
            ->orderBy('name');
    }

    /**
     * Get all backgrounds that teach this language
     */
    public function backgrounds(): MorphToMany
    {
        return $this->morphedByMany(
            Background::class,
            'reference',
            'entity_languages',
            'language_id',
            'reference_id'
        )
            ->withPivot('is_choice')
            ->orderBy('name');
    }
}
