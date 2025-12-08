<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Language - Lookup model for D&D languages (Common, Elvish, etc.).
 *
 * Table: languages
 *
 * Inverse relationships show which entities grant this language:
 * - races(): Races that speak this language
 * - backgrounds(): Backgrounds that teach this language
 */
class Language extends BaseModel
{
    protected $fillable = [
        'name',
        'slug',
        'full_slug',
        'script',
        'typical_speakers',
        'description',
        'is_learnable',
    ];

    protected $casts = [
        'is_learnable' => 'boolean',
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
