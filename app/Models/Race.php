<?php

namespace App\Models;

use App\Models\Concerns\HasConditions;
use App\Models\Concerns\HasEntityLanguages;
use App\Models\Concerns\HasEntitySpells;
use App\Models\Concerns\HasEntityTraits;
use App\Models\Concerns\HasLanguageScopes;
use App\Models\Concerns\HasModifiers;
use App\Models\Concerns\HasProficiencies;
use App\Models\Concerns\HasProficiencyScopes;
use App\Models\Concerns\HasSearchableHelpers;
use App\Models\Concerns\HasSenses;
use App\Models\Concerns\HasSources;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;
use Spatie\Tags\HasTags;

/**
 * @property bool $is_subrace Whether this race is a subrace (has a parent race)
 */
class Race extends BaseModel
{
    use HasConditions, HasEntityLanguages, HasEntitySpells, HasEntityTraits, HasLanguageScopes;
    use HasModifiers, HasProficiencies, HasProficiencyScopes, HasSearchableHelpers, HasSenses, HasSources;
    use HasTags, Searchable;

    protected $fillable = [
        'slug',
        'name',
        'size_id',
        'has_size_choice',
        'speed',
        'fly_speed',
        'swim_speed',
        'climb_speed',
        'parent_race_id',
        'subrace_required',
    ];

    protected $casts = [
        'size_id' => 'integer',
        'has_size_choice' => 'boolean',
        'speed' => 'integer',
        'fly_speed' => 'integer',
        'swim_speed' => 'integer',
        'climb_speed' => 'integer',
        'parent_race_id' => 'integer',
        'subrace_required' => 'boolean',
    ];

    protected $appends = [
        'is_subrace',
    ];

    /**
     * Determine if this race is a subrace (has a parent race).
     */
    public function getIsSubraceAttribute(): bool
    {
        return $this->parent_race_id !== null;
    }

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

    // Scout Searchable Methods
    public function toSearchableArray(): array
    {
        // Load relationships if not already loaded
        $this->loadMissing(['tags', 'spells', 'modifiers.abilityScore', 'senses.sense']);

        // Extract ability score bonuses from modifiers
        $abilityBonuses = $this->modifiers->where('modifier_category', 'ability_score');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'size_name' => $this->size?->name,
            'size_code' => $this->size?->code,
            'speed' => $this->speed,
            // Alternate movement speeds
            'fly_speed' => $this->fly_speed,
            'swim_speed' => $this->swim_speed,
            'climb_speed' => $this->climb_speed,
            'has_fly_speed' => $this->fly_speed !== null,
            'has_swim_speed' => $this->swim_speed !== null,
            'has_climb_speed' => $this->climb_speed !== null,
            'sources' => $this->getSearchableSourceNames(),
            'source_codes' => $this->getSearchableSourceCodes(),
            'is_subrace' => $this->parent_race_id !== null,
            'subrace_required' => $this->subrace_required,
            'parent_race_name' => $this->parent?->name,
            // Tag slugs for filtering (e.g., darkvision, fey_ancestry)
            'tag_slugs' => $this->getSearchableTagSlugs(),
            // Phase 3: Spell filtering (spells() now returns Spell models directly)
            'spell_slugs' => $this->spells->pluck('slug')->all(),
            'has_innate_spells' => $this->spells->isNotEmpty(),
            // Phase 4: Ability score bonuses (cast to int for Meilisearch filtering)
            'ability_str_bonus' => (int) ($abilityBonuses->firstWhere('abilityScore.code', 'STR')?->value ?? 0),
            'ability_dex_bonus' => (int) ($abilityBonuses->firstWhere('abilityScore.code', 'DEX')?->value ?? 0),
            'ability_con_bonus' => (int) ($abilityBonuses->firstWhere('abilityScore.code', 'CON')?->value ?? 0),
            'ability_int_bonus' => (int) ($abilityBonuses->firstWhere('abilityScore.code', 'INT')?->value ?? 0),
            'ability_wis_bonus' => (int) ($abilityBonuses->firstWhere('abilityScore.code', 'WIS')?->value ?? 0),
            'ability_cha_bonus' => (int) ($abilityBonuses->firstWhere('abilityScore.code', 'CHA')?->value ?? 0),
            // Senses (darkvision range for filtering)
            'has_darkvision' => $this->senses->contains(fn ($s) => $s->sense?->slug === 'darkvision'),
            'darkvision_range' => $this->senses->firstWhere(fn ($s) => $s->sense?->slug === 'darkvision')?->range_feet,
        ];
    }

    public function searchableWith(): array
    {
        return ['size', 'sources.source', 'parent', 'tags', 'spells', 'modifiers.abilityScore', 'senses.sense'];
    }

    public function searchableAs(): string
    {
        $prefix = config('scout.prefix');

        return $prefix.'races';
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
                'size_name',
                'size_code',
                'speed',
                // Alternate movement speeds
                'fly_speed',
                'swim_speed',
                'climb_speed',
                'has_fly_speed',
                'has_swim_speed',
                'has_climb_speed',
                'source_codes',
                'is_subrace',
                'subrace_required',
                'parent_race_name',
                'tag_slugs',
                // Phase 3: Spell filtering
                'spell_slugs',
                'has_innate_spells',
                // Phase 4: Ability score bonuses
                'ability_str_bonus',
                'ability_dex_bonus',
                'ability_con_bonus',
                'ability_int_bonus',
                'ability_wis_bonus',
                'ability_cha_bonus',
                // Senses
                'has_darkvision',
                'darkvision_range',
            ],
            'sortableAttributes' => [
                'name',
                'speed',
                'fly_speed',
                'swim_speed',
                'climb_speed',
                'darkvision_range',
            ],
            'searchableAttributes' => [
                'name',
                'size_name',
                'parent_race_name',
                'sources',
            ],
        ];
    }
}
