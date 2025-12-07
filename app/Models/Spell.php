<?php

namespace App\Models;

use App\Models\Concerns\HasDataTables;
use App\Models\Concerns\HasSearchableHelpers;
use App\Models\Concerns\HasSources;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Laravel\Scout\Searchable;
use Spatie\Tags\HasTags;

class Spell extends BaseModel
{
    use HasDataTables, HasSearchableHelpers, HasSources, HasTags, Searchable;

    protected $fillable = [
        'slug',
        'full_slug',
        'name',
        'level',
        'spell_school_id',
        'casting_time',
        'range',
        'components',
        'material_components',
        'duration',
        'needs_concentration',
        'is_ritual',
        'description',
        'higher_levels',
    ];

    protected $casts = [
        'level' => 'integer',
        'needs_concentration' => 'boolean',
        'is_ritual' => 'boolean',
    ];

    protected $appends = [
        'casting_time_type',
    ];

    /**
     * Parse casting time into a normalized type.
     *
     * Returns: action, bonus_action, reaction, minute, hour, special, unknown
     */
    public function getCastingTimeTypeAttribute(): string
    {
        $castingTime = strtolower($this->casting_time ?? '');

        if (empty($castingTime)) {
            return 'unknown';
        }

        return match (true) {
            str_contains($castingTime, 'bonus action') => 'bonus_action',
            str_contains($castingTime, 'reaction') => 'reaction',
            str_contains($castingTime, 'action') => 'action',
            str_contains($castingTime, 'minute') => 'minute',
            str_contains($castingTime, 'hour') => 'hour',
            str_contains($castingTime, 'special') => 'special',
            default => 'unknown',
        };
    }

    /**
     * Parse material component cost in gold pieces.
     *
     * Parses patterns like:
     * - "worth at least 25 gp"
     * - "worth 50 gp"
     * - "10 gp worth of"
     * - "worth at least 1,000 gp"
     *
     * Returns null if no cost is specified.
     *
     * Note: Handles ~90% of cases. Edge cases with unusual formatting may not parse correctly.
     */
    public function getMaterialCostGpAttribute(): ?int
    {
        $materials = $this->material_components;

        if (empty($materials)) {
            return null;
        }

        // Pattern 1: "worth at least X gp" or "worth X gp"
        if (preg_match('/worth(?:\s+at\s+least)?\s+([\d,]+)\s*gp/i', $materials, $matches)) {
            return (int) str_replace(',', '', $matches[1]);
        }

        // Pattern 2: "X gp worth of"
        if (preg_match('/([\d,]+)\s*gp\s+worth/i', $materials, $matches)) {
            return (int) str_replace(',', '', $matches[1]);
        }

        return null;
    }

    /**
     * Check if material components are consumed by the spell.
     *
     * Returns:
     * - true if materials are consumed
     * - false if materials exist but are not consumed
     * - null if spell has no material components
     *
     * Note: Handles ~90% of cases. Edge cases with unusual formatting may not parse correctly.
     */
    public function getMaterialConsumedAttribute(): ?bool
    {
        $materials = $this->material_components;

        if (empty($materials)) {
            return null;
        }

        $materialsLower = strtolower($materials);

        // Check for "consumes" or "consumed" patterns
        if (str_contains($materialsLower, 'consume')) {
            return true;
        }

        return false;
    }

    /**
     * Parse area of effect from spell description.
     *
     * Returns an array with:
     * - type: cone, sphere, cube, line, cylinder
     * - size: primary dimension in feet
     * - width: (lines only) width in feet
     * - height: (cylinders only) height in feet
     *
     * Returns null if no area of effect pattern is found.
     *
     * Note: Handles ~90% of cases. Edge cases with unusual formatting may not parse correctly.
     */
    public function getAreaOfEffectAttribute(): ?array
    {
        $description = $this->description;

        if (empty($description)) {
            return null;
        }

        // Cone: "15-foot cone"
        if (preg_match('/(\d+)[- ]foot[- ]cone/i', $description, $matches)) {
            return [
                'type' => 'cone',
                'size' => (int) $matches[1],
            ];
        }

        // Sphere: "20-foot-radius sphere"
        if (preg_match('/(\d+)[- ]foot[- ]radius\s+sphere/i', $description, $matches)) {
            return [
                'type' => 'sphere',
                'size' => (int) $matches[1],
            ];
        }

        // Cube: "15-foot cube"
        if (preg_match('/(\d+)[- ]foot[- ]cube/i', $description, $matches)) {
            return [
                'type' => 'cube',
                'size' => (int) $matches[1],
            ];
        }

        // Line: "100 feet long and 5 feet wide"
        if (preg_match('/(\d+)\s+feet\s+long\s+and\s+(\d+)\s+feet\s+wide/i', $description, $matches)) {
            return [
                'type' => 'line',
                'size' => (int) $matches[1],
                'width' => (int) $matches[2],
            ];
        }

        // Cylinder: "10-foot-radius, 40-foot-high cylinder"
        if (preg_match('/(\d+)[- ]foot[- ]radius[,\s]+(\d+)[- ]foot[- ]high\s+cylinder/i', $description, $matches)) {
            return [
                'type' => 'cylinder',
                'size' => (int) $matches[1],
                'height' => (int) $matches[2],
            ];
        }

        return null;
    }

    // Relationships
    public function spellSchool(): BelongsTo
    {
        return $this->belongsTo(SpellSchool::class);
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(CharacterClass::class, 'class_spells', 'spell_id', 'class_id');
    }

    public function effects(): HasMany
    {
        return $this->hasMany(SpellEffect::class);
    }

    public function savingThrows(): MorphToMany
    {
        return $this->morphToMany(
            AbilityScore::class,
            'reference',
            'entity_saving_throws',
            'reference_id',
            'ability_score_id'
        )
            ->withPivot('save_effect', 'is_initial_save', 'save_modifier')
            ->withTimestamps();
    }

    // Reverse relationships (entities that reference this spell)

    public function monsters(): MorphToMany
    {
        return $this->morphedByMany(
            Monster::class,
            'reference',
            'entity_spells',
            'spell_id',
            'reference_id'
        )->withPivot(['usage_limit', 'charges_cost_min', 'charges_cost_max']);
    }

    public function items(): MorphToMany
    {
        return $this->morphedByMany(
            Item::class,
            'reference',
            'entity_spells',
            'spell_id',
            'reference_id'
        )->withPivot(['usage_limit', 'charges_cost_min', 'charges_cost_max']);
    }

    public function races(): MorphToMany
    {
        return $this->morphedByMany(
            Race::class,
            'reference',
            'entity_spells',
            'spell_id',
            'reference_id'
        )->withPivot(['usage_limit', 'level_requirement']);
    }

    // Scout Search Configuration

    /**
     * Get the indexable data array for the model.
     */
    public function toSearchableArray(): array
    {
        // Load relationships to avoid N+1 queries
        $this->loadMissing(['spellSchool', 'sources.source', 'classes', 'tags', 'effects.damageType', 'savingThrows']);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'level' => $this->level,
            'school_name' => $this->spellSchool?->name,
            'school_code' => $this->spellSchool?->code,
            'casting_time' => $this->casting_time,
            'range' => $this->range,
            'components' => $this->components,
            'duration' => $this->duration,
            'concentration' => $this->needs_concentration,
            'ritual' => $this->is_ritual,
            'description' => $this->description,
            'at_higher_levels' => $this->higher_levels,
            'sources' => $this->getSearchableSourceNames(),
            'source_codes' => $this->getSearchableSourceCodes(),
            'classes' => $this->classes->pluck('name')->all(),
            'class_slugs' => $this->classes->pluck('slug')->all(),
            'tag_slugs' => $this->getSearchableTagSlugs(),
            // Damage types from spell effects (array of damage type codes)
            'damage_types' => $this->effects->filter(fn ($e) => $e->damageType)->pluck('damageType.code')->unique()->values()->all(),
            // Saving throws (array of ability codes like 'DEX', 'WIS')
            'saving_throws' => $this->savingThrows->pluck('code')->unique()->values()->all(),
            // Component breakdown (booleans parsed from components string)
            'requires_verbal' => str_contains($this->components ?? '', 'V'),
            'requires_somatic' => str_contains($this->components ?? '', 'S'),
            'requires_material' => str_contains($this->components ?? '', 'M'),
            // Effect types (array of effect type strings)
            'effect_types' => $this->effects->pluck('effect_type')->unique()->values()->all(),
            // Material component parsing (Issue #27)
            'material_cost_gp' => $this->material_cost_gp,
            'material_consumed' => $this->material_consumed,
            // Area of effect parsing (Issue #28)
            'aoe_type' => $this->area_of_effect['type'] ?? null,
            'aoe_size' => $this->area_of_effect['size'] ?? null,
        ];
    }

    /**
     * Get the relationships that should be eager loaded for search.
     */
    public function searchableWith(): array
    {
        return ['spellSchool', 'sources', 'classes', 'effects.damageType', 'savingThrows'];
    }

    /**
     * Get the name of the index associated with the model.
     */
    public function searchableAs(): string
    {
        $prefix = config('scout.prefix');

        return $prefix.'spells';
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
                'level',
                'school_name',
                'school_code',
                'casting_time',
                'range',
                'duration',
                'concentration',
                'ritual',
                'sources',
                'source_codes',
                'class_slugs',
                'tag_slugs',
                'damage_types',
                'saving_throws',
                'requires_verbal',
                'requires_somatic',
                'requires_material',
                'effect_types',
                // Material component fields (Issue #27)
                'material_cost_gp',
                'material_consumed',
                // Area of effect fields (Issue #28)
                'aoe_type',
                'aoe_size',
            ],
            'sortableAttributes' => [
                'name',
                'level',
            ],
            'searchableAttributes' => [
                'name',
                'description',
                'at_higher_levels',
                'school_name',
                'sources',
                'classes',
            ],
        ];
    }
}
