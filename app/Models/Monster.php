<?php

namespace App\Models;

use App\Models\Concerns\HasModifiers;
use App\Models\Concerns\HasSearchableHelpers;
use App\Models\Concerns\HasSenses;
use App\Models\Concerns\HasSources;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Laravel\Scout\Searchable;
use Spatie\Tags\HasTags;

class Monster extends BaseModel
{
    use HasModifiers, HasSearchableHelpers, HasSenses, HasSources, HasTags, Searchable;

    protected $fillable = [
        'name',
        'slug',
        'sort_name',
        'size_id',
        'creature_type_id',
        'type',
        'alignment',
        'armor_class',
        'armor_type',
        'hit_points_average',
        'hit_dice',
        'speed_walk',
        'speed_fly',
        'speed_swim',
        'speed_burrow',
        'speed_climb',
        'can_hover',
        'strength',
        'dexterity',
        'constitution',
        'intelligence',
        'wisdom',
        'charisma',
        'challenge_rating',
        'experience_points',
        'passive_perception',
        'languages',
        'description',
        'is_npc',
    ];

    protected $casts = [
        'can_hover' => 'boolean',
        'is_npc' => 'boolean',
    ];

    protected $appends = [
        'is_legendary',
        'proficiency_bonus',
    ];

    /**
     * Determine if this monster is legendary (has non-lair legendary actions).
     */
    public function getIsLegendaryAttribute(): bool
    {
        return $this->legendaryActions()
            ->where('is_lair_action', false)
            ->exists();
    }

    /**
     * Calculate proficiency bonus from challenge rating.
     *
     * Per D&D 5e rules (DMG p.274):
     * CR 0-4: +2, CR 5-8: +3, CR 9-12: +4, CR 13-16: +5,
     * CR 17-20: +6, CR 21-24: +7, CR 25-28: +8, CR 29+: +9
     */
    public function getProficiencyBonusAttribute(): int
    {
        $cr = $this->getChallengeRatingNumeric();

        return match (true) {
            $cr <= 4 => 2,
            $cr <= 8 => 3,
            $cr <= 12 => 4,
            $cr <= 16 => 5,
            $cr <= 20 => 6,
            $cr <= 24 => 7,
            $cr <= 28 => 8,
            default => 9,
        };
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class);
    }

    public function creatureType(): BelongsTo
    {
        return $this->belongsTo(CreatureType::class);
    }

    /**
     * Get monster traits from polymorphic entity_traits table.
     */
    public function entityTraits(): MorphMany
    {
        return $this->morphMany(CharacterTrait::class, 'reference')
            ->orderBy('sort_order');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(MonsterAction::class);
    }

    public function legendaryActions(): HasMany
    {
        return $this->hasMany(MonsterLegendaryAction::class);
    }

    /**
     * Get spells available to this monster.
     *
     * Returns Spell models directly via the polymorphic entity_spells pivot table.
     * Consistent naming with Item::spells() for unified API access.
     */
    public function spells(): MorphToMany
    {
        return $this->morphToMany(
            Spell::class,
            'reference',
            'entity_spells',
            'reference_id',
            'spell_id'
        )->withPivot([
            'ability_score_id',
            'level_requirement',
            'usage_limit',
            'is_cantrip',
        ]);
    }

    public function conditions(): MorphToMany
    {
        return $this->morphToMany(Condition::class, 'reference', 'entity_conditions')
            ->withPivot('description');
    }

    /**
     * Convert challenge rating string to numeric value for Meilisearch filtering.
     *
     * Converts fractional strings like "1/8", "1/4", "1/2" to float values (0.125, 0.25, 0.5).
     * Integer strings like "1", "10" are converted to float (1.0, 10.0).
     *
     * @return float The numeric challenge rating value
     */
    public function getChallengeRatingNumeric(): float
    {
        // Handle fractional challenge ratings (e.g., "1/8", "1/4", "1/2")
        if (str_contains($this->challenge_rating, '/')) {
            [$numerator, $denominator] = explode('/', $this->challenge_rating);

            return (float) $numerator / (float) $denominator;
        }

        // Handle integer challenge ratings (e.g., "1", "10", "20")
        return (float) $this->challenge_rating;
    }

    // Scout Search Configuration

    /**
     * Get the indexable data array for the model.
     */
    public function toSearchableArray(): array
    {
        // Load relationships to avoid N+1 queries
        $this->loadMissing(['size', 'sources.source', 'spells', 'tags', 'legendaryActions', 'actions', 'entityTraits', 'senses.sense', 'creatureType']);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'size_code' => $this->size?->code,
            'size_name' => $this->size?->name,
            'creature_type_slug' => $this->creatureType?->slug,
            'creature_type_name' => $this->creatureType?->name,
            'type' => $this->type,
            'alignment' => $this->alignment,
            'armor_class' => $this->armor_class,
            'armor_type' => $this->armor_type,
            'hit_points_average' => $this->hit_points_average,
            'challenge_rating' => $this->getChallengeRatingNumeric(),
            'experience_points' => $this->experience_points,
            'description' => $this->description,
            // Speed attributes (for mobility filtering)
            'speed_walk' => $this->speed_walk,
            'speed_fly' => $this->speed_fly,
            'speed_swim' => $this->speed_swim,
            'speed_burrow' => $this->speed_burrow,
            'speed_climb' => $this->speed_climb,
            'can_hover' => $this->can_hover,
            // Ability scores (for stat-based filtering)
            'strength' => $this->strength,
            'dexterity' => $this->dexterity,
            'constitution' => $this->constitution,
            'intelligence' => $this->intelligence,
            'wisdom' => $this->wisdom,
            'charisma' => $this->charisma,
            // Perception and NPC status
            'passive_perception' => $this->passive_perception,
            'is_npc' => $this->is_npc,
            'sources' => $this->getSearchableSourceNames(),
            'source_codes' => $this->getSearchableSourceCodes(),
            // Spell slugs for fast Meilisearch filtering (1,098 relationships for 129 spellcasters)
            'spell_slugs' => $this->spells->pluck('slug')->all(),
            // Tag slugs for filtering (e.g., fire_immune, undead, construct)
            'tag_slugs' => $this->getSearchableTagSlugs(),
            // Phase 3: Boolean capability flags
            'has_legendary_actions' => $this->legendaryActions->where('is_lair_action', 0)->isNotEmpty(),
            'has_lair_actions' => $this->legendaryActions->where('is_lair_action', 1)->isNotEmpty(),
            'is_spellcaster' => $this->spells->isNotEmpty(),
            'has_reactions' => $this->actions->where('action_type', 'reaction')->isNotEmpty(),
            // Phase 4: Trait-based capability flags
            'has_legendary_resistance' => $this->entityTraits->contains(fn ($t) => str_contains($t->name, 'Legendary Resistance')),
            'has_magic_resistance' => $this->entityTraits->contains('name', 'Magic Resistance'),
            // Phase 5: Senses (darkvision, blindsight, tremorsense, truesight)
            'sense_types' => $this->senses->pluck('sense.slug')->all(),
            'has_darkvision' => $this->senses->contains(fn ($s) => $s->sense?->slug === 'core:darkvision'),
            'darkvision_range' => $this->senses->firstWhere(fn ($s) => $s->sense?->slug === 'core:darkvision')?->range_feet,
            'has_blindsight' => $this->senses->contains(fn ($s) => $s->sense?->slug === 'core:blindsight'),
            'has_tremorsense' => $this->senses->contains(fn ($s) => $s->sense?->slug === 'core:tremorsense'),
            'has_truesight' => $this->senses->contains(fn ($s) => $s->sense?->slug === 'core:truesight'),
        ];
    }

    /**
     * Get the name of the index associated with the model.
     */
    public function searchableAs(): string
    {
        $prefix = config('scout.prefix');

        return $prefix.'monsters';
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
                'size_code',
                'size_name',
                'creature_type_slug',
                'type',
                'alignment',
                'armor_class',
                'armor_type',
                'hit_points_average',
                'challenge_rating',
                'experience_points',
                'source_codes',
                'spell_slugs',
                'tag_slugs',
                'speed_walk',
                'speed_fly',
                'speed_swim',
                'speed_burrow',
                'speed_climb',
                'can_hover',
                'strength',
                'dexterity',
                'constitution',
                'intelligence',
                'wisdom',
                'charisma',
                'passive_perception',
                'is_npc',
                'has_legendary_actions',
                'has_lair_actions',
                'is_spellcaster',
                'has_reactions',
                'has_legendary_resistance',
                'has_magic_resistance',
                'sense_types',
                'has_darkvision',
                'darkvision_range',
                'has_blindsight',
                'has_tremorsense',
                'has_truesight',
            ],
            'sortableAttributes' => [
                'name',
                'armor_class',
                'hit_points_average',
                'challenge_rating',
                'experience_points',
                'speed_walk',
                'strength',
                'dexterity',
                'passive_perception',
            ],
            'searchableAttributes' => [
                'name',
                'description',
                'type',
                'alignment',
                'sources',
            ],
        ];
    }
}
