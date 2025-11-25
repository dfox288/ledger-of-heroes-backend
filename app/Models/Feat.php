<?php

namespace App\Models;

use App\Models\Concerns\HasProficiencyScopes;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Laravel\Scout\Searchable;
use Spatie\Tags\HasTags;

class Feat extends BaseModel
{
    use HasProficiencyScopes, HasTags, Searchable;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'slug',
        'prerequisites_text',
        'description',
    ];

    /**
     * Get all sources for this feat (polymorphic).
     */
    public function sources(): MorphMany
    {
        return $this->morphMany(EntitySource::class, 'reference');
    }

    /**
     * Get all modifiers for this feat (polymorphic).
     */
    public function modifiers(): MorphMany
    {
        return $this->morphMany(Modifier::class, 'reference');
    }

    /**
     * Get all proficiencies granted by this feat (polymorphic).
     */
    public function proficiencies(): MorphMany
    {
        return $this->morphMany(Proficiency::class, 'reference');
    }

    /**
     * Get all conditions for this feat (polymorphic).
     */
    public function conditions(): MorphMany
    {
        return $this->morphMany(EntityCondition::class, 'reference');
    }

    /**
     * Get all prerequisites for this feat (polymorphic).
     */
    public function prerequisites(): MorphMany
    {
        return $this->morphMany(EntityPrerequisite::class, 'reference');
    }

    /**
     * Scope a query to search feats.
     */
    public function scopeSearch($query, $searchTerm)
    {
        return $query->where('name', 'LIKE', "%{$searchTerm}%")
            ->orWhere('description', 'LIKE', "%{$searchTerm}%")
            ->orWhere('prerequisites_text', 'LIKE', "%{$searchTerm}%");
    }

    /**
     * Scope: Filter by prerequisite race
     * Usage: Feat::wherePrerequisiteRace('dwarf')->get()
     */
    public function scopeWherePrerequisiteRace($query, string $raceName)
    {
        return $query->whereHas('prerequisites', function ($q) use ($raceName) {
            $q->where('prerequisite_type', Race::class)
                ->whereHas('prerequisite', function ($raceQuery) use ($raceName) {
                    $raceQuery->where('name', 'LIKE', "%{$raceName}%");
                });
        });
    }

    /**
     * Scope: Filter by prerequisite ability score
     * Usage: Feat::wherePrerequisiteAbility('strength', 13)->get()
     */
    public function scopeWherePrerequisiteAbility($query, string $abilityName, ?int $minValue = null)
    {
        return $query->whereHas('prerequisites', function ($q) use ($abilityName, $minValue) {
            $q->where('prerequisite_type', AbilityScore::class)
                ->whereHas('prerequisite', function ($abilityQuery) use ($abilityName) {
                    $abilityQuery->where('code', strtoupper($abilityName))
                        ->orWhere('name', 'LIKE', "%{$abilityName}%");
                });

            if ($minValue !== null) {
                $q->where('minimum_value', '>=', $minValue);
            }
        });
    }

    /**
     * Scope: Filter by presence of prerequisites
     * Usage: Feat::withOrWithoutPrerequisites(false)->get() // feats without prereqs
     */
    public function scopeWithOrWithoutPrerequisites($query, bool $hasPrerequisites)
    {
        if ($hasPrerequisites) {
            return $query->has('prerequisites');
        }

        return $query->doesntHave('prerequisites');
    }

    /**
     * Scope: Filter by prerequisite proficiency
     * Usage: Feat::wherePrerequisiteProficiency('medium armor')->get()
     */
    public function scopeWherePrerequisiteProficiency($query, string $proficiencyName)
    {
        return $query->whereHas('prerequisites', function ($q) use ($proficiencyName) {
            $q->where('prerequisite_type', ProficiencyType::class)
                ->whereHas('prerequisite', function ($profQuery) use ($proficiencyName) {
                    $profQuery->where('name', 'LIKE', "%{$proficiencyName}%");
                });
        });
    }

    // Scout Searchable Methods
    public function toSearchableArray(): array
    {
        // Load tags relationship if not already loaded
        $this->loadMissing(['tags', 'prerequisites.prerequisite', 'modifiers.abilityScore', 'proficiencies']);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'prerequisites_text' => $this->prerequisites_text,
            'sources' => $this->sources->pluck('source.name')->unique()->values()->all(),
            'source_codes' => $this->sources->pluck('source.code')->unique()->values()->all(),
            // Tag slugs for filtering (e.g., combat, magic, skill_improvement)
            'tag_slugs' => $this->tags->pluck('slug')->all(),
            // Phase 3: Boolean filters
            'has_prerequisites' => $this->prerequisites->isNotEmpty(),
            'grants_proficiencies' => $this->proficiencies->isNotEmpty(),
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
        ];
    }

    public function searchableWith(): array
    {
        return ['sources.source', 'tags', 'prerequisites.prerequisite', 'modifiers.abilityScore', 'proficiencies'];
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
                // Phase 4: Array filters
                'improved_abilities',
                'prerequisite_types',
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
}
