<?php

namespace App\Models;

use App\Models\Concerns\HasEntityLanguages;
use App\Models\Concerns\HasEntityTraits;
use App\Models\Concerns\HasLanguageScopes;
use App\Models\Concerns\HasProficiencies;
use App\Models\Concerns\HasProficiencyScopes;
use App\Models\Concerns\HasSearchableHelpers;
use App\Models\Concerns\HasSources;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Laravel\Scout\Searchable;
use Spatie\Tags\HasTags;

class Background extends BaseModel
{
    use HasEntityLanguages, HasEntityTraits, HasLanguageScopes, HasProficiencies;
    use HasProficiencyScopes, HasSearchableHelpers, HasSources, HasTags, Searchable;

    protected $fillable = [
        'slug',
        'full_slug',
        'name',
    ];

    /**
     * Get the background's feature name (cleaned of "Feature: " prefix).
     *
     * Background features are stored as traits with category='feature'.
     * The trait name typically has format "Feature: Shelter of the Faithful".
     * This accessor strips the "Feature: " prefix for cleaner display.
     */
    public function getFeatureNameAttribute(): ?string
    {
        $featureTrait = $this->traits->firstWhere('category', 'feature');

        if (! $featureTrait) {
            return null;
        }

        // Strip "Feature: " prefix if present
        $name = $featureTrait->name;

        if (str_starts_with($name, 'Feature: ')) {
            return substr($name, 9); // Length of "Feature: "
        }

        return $name;
    }

    /**
     * Get the background's feature description.
     *
     * Returns the description of the trait with category='feature'.
     */
    public function getFeatureDescriptionAttribute(): ?string
    {
        $featureTrait = $this->traits->firstWhere('category', 'feature');

        return $featureTrait?->description;
    }

    // Polymorphic relationships
    public function equipment(): MorphMany
    {
        return $this->morphMany(EntityItem::class, 'reference');
    }

    // Scout Searchable Methods
    public function toSearchableArray(): array
    {
        // Load relationships needed for indexing
        $this->loadMissing(['tags', 'proficiencies.skill', 'languages', 'traits']);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sources' => $this->getSearchableSourceNames(),
            'source_codes' => $this->getSearchableSourceCodes(),
            // Tag slugs for filtering (e.g., criminal, noble, guild_member)
            'tag_slugs' => $this->getSearchableTagSlugs(),
            // Phase 3: Language choice
            'grants_language_choice' => $this->languages->count() > 0,
            // Phase 4: Proficiencies (HIGH VALUE)
            'skill_proficiencies' => $this->proficiencies
                ->where('proficiency_type', 'skill')
                ->filter(fn ($p) => $p->skill)
                ->pluck('skill.slug')
                ->unique()->values()->all(),
            'tool_proficiency_types' => $this->proficiencies
                ->where('proficiency_type', 'tool')
                ->pluck('proficiency_subcategory')
                ->filter()
                ->unique()->values()->all(),
            // Feature name for searching (e.g., "Shelter of the Faithful")
            'feature_name' => $this->feature_name,
        ];
    }

    public function searchableWith(): array
    {
        return ['sources.source', 'tags', 'proficiencies.skill', 'languages', 'traits'];
    }

    public function searchableAs(): string
    {
        $prefix = config('scout.prefix');

        return $prefix.'backgrounds';
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
                'name',
                'source_codes',
                'tag_slugs',
                'grants_language_choice',
                'skill_proficiencies',
                'tool_proficiency_types',
                'feature_name',
            ],
            'sortableAttributes' => [
                'name',
                'feature_name',
            ],
            'searchableAttributes' => [
                'name',
                'sources',
                'feature_name',
            ],
        ];
    }
}
