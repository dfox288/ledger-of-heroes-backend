<?php

namespace App\Models;

use App\Enums\ResetTiming;
use App\Models\Concerns\HasConditions;
use App\Models\Concerns\HasEntityLanguages;
use App\Models\Concerns\HasEntitySpells;
use App\Models\Concerns\HasModifiers;
use App\Models\Concerns\HasPrerequisites;
use App\Models\Concerns\HasProficiencies;
use App\Models\Concerns\HasProficiencyScopes;
use App\Models\Concerns\HasSearchableHelpers;
use App\Models\Concerns\HasSources;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;
use Spatie\Tags\HasTags;

class Feat extends BaseModel
{
    use HasConditions, HasEntityLanguages, HasEntitySpells, HasModifiers, HasPrerequisites, HasProficiencies;
    use HasProficiencyScopes, HasSearchableHelpers, HasSources, HasTags, Searchable;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'slug',
        'full_slug',
        'prerequisites_text',
        'description',
        'resets_on',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'resets_on' => ResetTiming::class,
    ];

    // =========================================================================
    // Computed Accessors
    // =========================================================================

    /**
     * Check if this is a "half-feat" (grants +1 to an ability score).
     *
     * Half-feats are feats that provide both a +1 ability score increase
     * and other benefits, making them popular choices for odd ability scores.
     */
    public function getIsHalfFeatAttribute(): bool
    {
        $this->loadMissing('modifiers');

        return $this->modifiers
            ->where('modifier_category', 'ability_score')
            ->where('value', '1')
            ->isNotEmpty();
    }

    /**
     * Get the parent feat slug for variant feats.
     *
     * Variant feats like "Resilient (Constitution)" or "Elemental Adept (Fire)"
     * share a common parent. This returns the slugified base name.
     *
     * Returns null for non-variant feats (those without parentheses).
     */
    public function getParentFeatSlugAttribute(): ?string
    {
        // Check if name contains parentheses (variant indicator)
        if (! str_contains($this->name, '(')) {
            return null;
        }

        // Extract base name before the parentheses
        $baseName = trim(explode('(', $this->name)[0]);

        // Convert to slug format
        return \Illuminate\Support\Str::slug($baseName);
    }

    // =========================================================================
    // Scout Searchable Methods
    public function toSearchableArray(): array
    {
        // Load tags relationship if not already loaded
        $this->loadMissing(['tags', 'prerequisites.prerequisite', 'modifiers.abilityScore', 'proficiencies', 'spells']);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'prerequisites_text' => $this->prerequisites_text,
            'sources' => $this->getSearchableSourceNames(),
            'source_codes' => $this->getSearchableSourceCodes(),
            // Tag slugs for filtering (e.g., combat, magic, skill_improvement)
            'tag_slugs' => $this->getSearchableTagSlugs(),
            // Phase 3: Boolean filters
            'has_prerequisites' => $this->prerequisites->isNotEmpty(),
            'grants_proficiencies' => $this->proficiencies->isNotEmpty(),
            'grants_spells' => $this->spells->isNotEmpty(),
            // Phase 4: Array filters
            'improved_abilities' => $this->modifiers
                ->where('modifier_category', 'ability_score')
                ->whereNotNull('ability_score_id')
                ->pluck('abilityScore.code')
                ->unique()->values()->all(),
            'prerequisite_types' => $this->prerequisites
                ->whereNotNull('prerequisite_type')
                ->pluck('prerequisite_type')
                ->map(fn ($type) => class_basename($type))
                ->unique()->values()->all(),
            // Computed fields
            'is_half_feat' => $this->is_half_feat,
            'parent_feat_slug' => $this->parent_feat_slug,
        ];
    }

    public function searchableWith(): array
    {
        return ['sources.source', 'tags', 'prerequisites.prerequisite', 'modifiers.abilityScore', 'modifiers.skill', 'proficiencies', 'spells.spellSchool', 'languages.language'];
    }

    public function searchableAs(): string
    {
        $prefix = config('scout.prefix');

        return $prefix.'feats';
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
                'source_codes',
                'tag_slugs',
                // Phase 3: Boolean filters
                'has_prerequisites',
                'grants_proficiencies',
                'grants_spells',
                // Phase 4: Array filters
                'improved_abilities',
                'prerequisite_types',
                // Computed fields
                'is_half_feat',
                'parent_feat_slug',
            ],
            'sortableAttributes' => [
                'name',
            ],
            'searchableAttributes' => [
                'name',
                'description',
                'prerequisites_text',
                'sources',
            ],
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    /**
     * Get the counters (usage limits) for this feat.
     *
     * Feats with limited uses (like Lucky with 3 luck points) have
     * counter records storing the base uses and reset timing.
     */
    public function counters(): HasMany
    {
        return $this->hasMany(ClassCounter::class);
    }
}
