<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Laravel\Scout\Searchable;
use Spatie\Tags\HasTags;

class Spell extends BaseModel
{
    use HasTags, Searchable;

    protected $fillable = [
        'slug',
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

    public function sources(): MorphMany
    {
        return $this->morphMany(EntitySource::class, 'reference', 'reference_type', 'reference_id');
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

    public function randomTables(): MorphMany
    {
        return $this->morphMany(RandomTable::class, 'reference');
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

    // Scopes for API filtering
    public function scopeLevel($query, $level)
    {
        return $query->where('level', $level);
    }

    public function scopeSchool($query, $schoolIdentifier)
    {
        // Accept ID, code (EV, EN), or name (evocation, enchantment)
        if (is_numeric($schoolIdentifier)) {
            return $query->where('spell_school_id', $schoolIdentifier);
        }

        // Resolve by code or name
        $school = SpellSchool::where('code', strtoupper($schoolIdentifier))
            ->orWhere('name', 'LIKE', $schoolIdentifier)
            ->first();

        return $school
            ? $query->where('spell_school_id', $school->id)
            : $query->whereRaw('1 = 0'); // No results if school not found
    }

    public function scopeConcentration($query, $needsConcentration)
    {
        return $query->where('needs_concentration', $needsConcentration);
    }

    public function scopeRitual($query, $isRitual)
    {
        return $query->where('is_ritual', $isRitual);
    }

    public function scopeSearch($query, $searchTerm)
    {
        $driver = $query->getConnection()->getDriverName();

        if ($driver === 'mysql') {
            // Search name with LIKE (prioritizes name matches)
            return $query->where('name', 'LIKE', "%{$searchTerm}%");
        }

        // Fallback to LIKE search for other databases (e.g., SQLite for testing)
        return $query->where(function ($q) use ($searchTerm) {
            $q->where('name', 'LIKE', "%{$searchTerm}%")
                ->orWhere('description', 'LIKE', "%{$searchTerm}%");
        });
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
            'sources' => $this->sources->pluck('source.name')->all(),
            'source_codes' => $this->sources->pluck('source.code')->all(),
            'classes' => $this->classes->pluck('name')->all(),
            'class_slugs' => $this->classes->pluck('slug')->all(),
            'tag_slugs' => $this->tags->pluck('slug')->all(),
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
