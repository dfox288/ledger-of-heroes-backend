<?php

namespace App\Models;

use App\Models\Concerns\HasEntityTraits;
use App\Models\Concerns\HasModifiers;
use App\Models\Concerns\HasProficiencies;
use App\Models\Concerns\HasProficiencyScopes;
use App\Models\Concerns\HasSearchableHelpers;
use App\Models\Concerns\HasSources;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Laravel\Scout\Searchable;
use Spatie\Tags\HasTags;

/**
 * @property int $id
 * @property int $hit_die Hit die value (e.g., 8, 10, 12)
 * @property int|null $parent_class_id Parent class ID for subclasses
 * @property bool $is_base_class Whether this is a base class (not a subclass)
 * @property int|null $subclass_level Level at which subclass is chosen
 * @property int $effective_hit_die Effective hit die (inherits from parent for subclasses)
 * @property AbilityScore|null $effective_spellcasting_ability Effective spellcasting ability
 */
class CharacterClass extends BaseModel
{
    use HasEntityTraits, HasModifiers, HasProficiencies, HasProficiencyScopes, HasSearchableHelpers, HasSources, HasTags, Searchable;

    protected $table = 'classes';

    protected $fillable = [
        'slug',
        'full_slug',
        'name',
        'parent_class_id',
        'hit_die',
        'description',
        'archetype',
        'primary_ability',
        'spellcasting_ability_id',
    ];

    protected $casts = [
        'hit_die' => 'integer',
        'parent_class_id' => 'integer',
        'spellcasting_ability_id' => 'integer',
    ];

    protected $appends = [
        // Note: effective_hit_die accessor exists but is not auto-appended
        // ClassResource explicitly calls $this->effective_hit_die for the hit_die field
        'effective_spellcasting_ability',
    ];

    /**
     * Get the effective hit die, inheriting from parent class if needed.
     *
     * D&D Context: Subclasses inherit hit dice from their base class.
     * A Death Domain Cleric uses d8 (from Cleric), not d0.
     */
    public function getEffectiveHitDieAttribute(): int
    {
        // If this class has a valid hit_die, use it
        if ($this->hit_die > 0) {
            return $this->hit_die;
        }

        // Subclass with hit_die = 0: inherit from parent
        if ($this->parent_class_id !== null) {
            // Use the relationship if loaded, otherwise query
            $parent = $this->relationLoaded('parentClass')
                ? $this->parentClass
                : $this->parentClass()->first();

            return $parent?->hit_die ?? 0;
        }

        return 0;
    }

    /**
     * Get the effective spellcasting ability, inheriting from parent class if needed.
     *
     * D&D Context: Subclasses inherit spellcasting ability from their base class.
     * A Death Domain Cleric uses Wisdom (from Cleric), not null.
     */
    public function getEffectiveSpellcastingAbilityAttribute(): ?AbilityScore
    {
        // If this class has a spellcasting ability, use it
        if ($this->spellcasting_ability_id !== null) {
            return $this->relationLoaded('spellcastingAbility')
                ? $this->spellcastingAbility
                : $this->spellcastingAbility()->first();
        }

        // Subclass with null spellcasting_ability_id: inherit from parent
        if ($this->parent_class_id !== null) {
            $parent = $this->relationLoaded('parentClass')
                ? $this->parentClass
                : $this->parentClass()->first();

            if ($parent?->spellcasting_ability_id !== null) {
                return $parent->relationLoaded('spellcastingAbility')
                    ? $parent->spellcastingAbility
                    : $parent->spellcastingAbility()->first();
            }
        }

        return null;
    }

    // Relationships
    public function spellcastingAbility(): BelongsTo
    {
        return $this->belongsTo(AbilityScore::class, 'spellcasting_ability_id');
    }

    public function parentClass(): BelongsTo
    {
        return $this->belongsTo(CharacterClass::class, 'parent_class_id');
    }

    public function subclasses(): HasMany
    {
        return $this->hasMany(CharacterClass::class, 'parent_class_id');
    }

    public function features(): HasMany
    {
        return $this->hasMany(ClassFeature::class, 'class_id');
    }

    public function levelProgression(): HasMany
    {
        return $this->hasMany(ClassLevelProgression::class, 'class_id');
    }

    public function counters(): HasMany
    {
        return $this->hasMany(ClassCounter::class, 'class_id');
    }

    /**
     * Get multiclass ability score requirements.
     *
     * Stored in entity_proficiencies with proficiency_type='multiclass_requirement'.
     * is_choice=true means OR condition (need any one), is_choice=false means AND (need all).
     */
    public function multiclassRequirements(): MorphMany
    {
        return $this->morphMany(Proficiency::class, 'reference')
            ->where('proficiency_type', 'multiclass_requirement');
    }

    public function spells(): BelongsToMany
    {
        return $this->belongsToMany(Spell::class, 'class_spells', 'class_id', 'spell_id');
    }

    /**
     * Get optional features available to this class.
     * Uses Laravel's alphabetical pivot table naming: class_optional_feature
     */
    public function optionalFeatures(): BelongsToMany
    {
        return $this->belongsToMany(OptionalFeature::class, 'class_optional_feature', 'class_id', 'optional_feature_id')
            ->withPivot('subclass_name')
            ->withTimestamps();
    }

    public function equipment(): MorphMany
    {
        return $this->morphMany(EntityItem::class, 'reference');
    }

    // Computed property
    public function getIsBaseClassAttribute(): bool
    {
        return is_null($this->parent_class_id);
    }

    /**
     * Get the spellcasting type based on max spell level.
     *
     * D&D 5e caster classifications:
     * - 'full': 9th level spells (Bard, Cleric, Druid, Sorcerer, Wizard)
     * - 'half': 5th level spells (Paladin, Ranger, Artificer)
     * - 'third': 4th level spells (Eldritch Knight, Arcane Trickster)
     * - 'pact': Warlock (unique pact magic system, 5th level spells)
     * - 'none': Non-spellcasters (Barbarian, Fighter, Monk, Rogue base)
     *
     * Note: Warlock is detected by name since pact magic differs from slot-based casting.
     * This accessor requires levelProgression to be loaded for accurate results.
     */
    public function getSpellcastingTypeAttribute(): string
    {
        // Warlock uses pact magic (unique system)
        $className = $this->parent_class_id !== null && $this->parentClass
            ? $this->parentClass->name
            : $this->name;

        if (strtolower($className) === 'warlock') {
            return 'pact';
        }

        // No spellcasting ability means non-caster
        if ($this->spellcasting_ability_id === null && $this->effective_spellcasting_ability === null) {
            return 'none';
        }

        // Determine from max spell level if levelProgression is loaded
        // For subclasses, check parent's progression if own is empty
        $progression = null;

        if ($this->relationLoaded('levelProgression') && $this->levelProgression->isNotEmpty()) {
            $progression = $this->levelProgression;
        } elseif ($this->parent_class_id !== null
            && $this->relationLoaded('parentClass')
            && $this->parentClass
            && $this->parentClass->relationLoaded('levelProgression')
            && $this->parentClass->levelProgression->isNotEmpty()) {
            $progression = $this->parentClass->levelProgression;
        }

        if ($progression !== null) {
            $ordinals = ['1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th'];
            $maxLevel = 0;

            for ($i = 1; $i <= 9; $i++) {
                $column = "spell_slots_{$ordinals[$i - 1]}";
                if ($progression->max($column) > 0) {
                    $maxLevel = $i;
                }
            }

            return match ($maxLevel) {
                9 => 'full',
                5 => 'half',
                4 => 'third',
                default => $maxLevel > 0 ? 'other' : 'none',
            };
        }

        // Fallback: has spellcasting ability but no progression loaded
        return 'unknown';
    }

    /**
     * Get the level at which this class gains its subclass.
     *
     * D&D Context: Different classes get subclasses at different levels:
     * - Level 1: Cleric (Divine Domain), Sorcerer, Warlock
     * - Level 2: Druid, Wizard
     * - Level 3: All others (Barbarian, Bard, Fighter, Monk, Paladin, Ranger, Rogue)
     *
     * This is derived from the minimum level of any subclass's first feature.
     * Only meaningful for base classes - subclasses return null.
     */
    public function getSubclassLevelAttribute(): ?int
    {
        // Only meaningful for base classes
        if (! $this->is_base_class) {
            return null;
        }

        // Get minimum level from any subclass's features
        $minLevel = $this->subclasses()
            ->join('class_features', 'classes.id', '=', 'class_features.class_id')
            ->min('class_features.level');

        return $minLevel ? (int) $minLevel : null;
    }

    /**
     * Get pre-computed hit points data for display.
     *
     * Calculates D&D 5e hit point formulas:
     * - First level: max hit die + CON modifier
     * - Higher levels: roll or average + CON modifier
     *
     * @return array{
     *   hit_die: string,
     *   hit_die_numeric: int,
     *   first_level: array{value: int, description: string},
     *   higher_levels: array{roll: string, average: int, description: string}
     * }|null
     */
    public function getHitPointsAttribute(): ?array
    {
        $hitDie = $this->effective_hit_die;

        if (! $hitDie) {
            return null;
        }

        $average = (int) floor($hitDie / 2) + 1;
        // For subclasses, use parent class name for the description
        $className = $this->parent_class_id !== null && $this->parentClass
            ? strtolower($this->parentClass->name)
            : strtolower($this->name);

        return [
            'hit_die' => "d{$hitDie}",
            'hit_die_numeric' => $hitDie,
            'first_level' => [
                'value' => $hitDie,
                'description' => "{$hitDie} + your Constitution modifier",
            ],
            'higher_levels' => [
                'roll' => "1d{$hitDie}",
                'average' => $average,
                'description' => "1d{$hitDie} (or {$average}) + your Constitution modifier per {$className} level after 1st",
            ],
        ];
    }

    /**
     * Get spell slot summary for display optimization.
     *
     * Tells frontend which spell slot columns to render without scanning all rows.
     * Returns null if levelProgression relationship is not loaded.
     *
     * @return array{
     *   has_spell_slots: bool,
     *   max_spell_level: int|null,
     *   available_levels: array<int>,
     *   has_cantrips: bool,
     *   caster_type: string|null
     * }|null
     */
    public function getSpellSlotSummaryAttribute(): ?array
    {
        // Must have level progression loaded
        if (! $this->relationLoaded('levelProgression')) {
            return null;
        }

        $progression = $this->levelProgression;
        if ($progression->isEmpty()) {
            return null;
        }

        // Determine max spell level with slots
        $maxLevel = 0;
        $ordinals = ['1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th'];
        for ($i = 1; $i <= 9; $i++) {
            $column = "spell_slots_{$ordinals[$i - 1]}";
            if ($progression->max($column) > 0) {
                $maxLevel = $i;
            }
        }

        // Determine caster type based on max spell level
        $casterType = match ($maxLevel) {
            9 => 'full',      // Wizard, Cleric, etc.
            5 => 'half',      // Paladin, Ranger
            4 => 'third',     // Eldritch Knight, Arcane Trickster
            0 => null,        // Non-caster
            default => 'other',
        };

        return [
            'has_spell_slots' => $maxLevel > 0,
            'max_spell_level' => $maxLevel > 0 ? $maxLevel : null,
            'available_levels' => $maxLevel > 0 ? range(1, $maxLevel) : [],
            'has_cantrips' => ($progression->max('cantrips_known') ?? 0) > 0,
            'caster_type' => $casterType,
        ];
    }

    /**
     * Get all top-level features including inherited base class features.
     *
     * Returns only parent features (not choice options). Choice options are
     * nested under their parent features via the `childFeatures` relationship.
     *
     * For subclasses, merges parent class features with subclass-specific features.
     * Features are sorted by level, then by sort_order to maintain proper ordering.
     *
     * @param  bool  $includeInherited  Whether to include parent class features (default: true)
     * @return \Illuminate\Support\Collection<ClassFeature> Top-level features only
     */
    public function getAllFeatures(bool $includeInherited = true)
    {
        // Filter to top-level features only (exclude choice options)
        // Choice options are nested under their parent via childFeatures relationship
        $filterTopLevel = fn ($features) => $features
            ->whereNull('parent_feature_id')
            ->sortBy([
                ['level', 'asc'],
                ['sort_order', 'asc'],
            ])
            ->values();

        // Base classes or when inheritance disabled: return only this class's features
        if (! $includeInherited || $this->parent_class_id === null) {
            return $filterTopLevel($this->features);
        }

        // Subclasses: merge parent + subclass features
        // Only if parent relationship and its features are loaded
        if ($this->relationLoaded('parentClass') && $this->parentClass->relationLoaded('features')) {
            $merged = $this->parentClass->features->concat($this->features);

            return $filterTopLevel($merged);
        }

        // Fallback: If parent features not loaded, return only subclass features
        return $filterTopLevel($this->features);
    }

    /**
     * Calculate proficiency bonus for a given level.
     *
     * D&D 5e formula: floor((level - 1) / 4) + 2
     * Level 1-4: +2, Level 5-8: +3, Level 9-12: +4, Level 13-16: +5, Level 17-20: +6
     */
    public static function proficiencyBonusForLevel(int $level): int
    {
        return (int) floor(($level - 1) / 4) + 2;
    }

    /**
     * Get formatted proficiency bonus string with plus sign.
     */
    public static function formattedProficiencyBonus(int $level): string
    {
        return '+'.self::proficiencyBonusForLevel($level);
    }

    // Scout Searchable Methods
    public function toSearchableArray(): array
    {
        // Load relationships if not already loaded
        $this->loadMissing(['tags', 'proficiencies.skill', 'proficiencies.proficiencyType', 'spells', 'optionalFeatures']);

        // Calculate max spell level
        $maxSpellLevel = $this->spells->max('level');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            // Use effective_hit_die to inherit from parent for subclasses
            // This ensures filtering matches API response (which uses effective_hit_die)
            'hit_die' => $this->effective_hit_die,
            'description' => $this->description,
            'archetype' => $this->archetype,
            'primary_ability' => $this->primary_ability,
            // Use effective_spellcasting_ability to inherit from parent for subclasses
            'spellcasting_ability' => $this->effective_spellcasting_ability?->code,
            'is_spellcaster' => $this->effective_spellcasting_ability !== null,
            'sources' => $this->getSearchableSourceNames(),
            'source_codes' => $this->getSearchableSourceCodes(),
            'is_subclass' => $this->parent_class_id !== null,
            'is_base_class' => $this->parent_class_id === null,
            'parent_class_name' => $this->parentClass?->name,
            // Tag slugs for filtering (e.g., spellcaster, martial, half_caster)
            'tag_slugs' => $this->getSearchableTagSlugs(),
            // Phase 3: Spell counts (quick wins)
            'has_spells' => $this->spells_count > 0,
            'spell_count' => $this->spells_count,
            'max_spell_level' => $maxSpellLevel !== null ? (int) $maxSpellLevel : null,
            // Phase 4: Proficiencies (high value filtering)
            'saving_throw_proficiencies' => $this->proficiencies
                ->where('proficiency_type', 'saving_throw')
                ->pluck('proficiency_name')
                ->values()->all(),
            'armor_proficiencies' => $this->proficiencies
                ->where('proficiency_type', 'armor')
                ->pluck('proficiency_name')
                ->values()->all(),
            'weapon_proficiencies' => $this->proficiencies
                ->where('proficiency_type', 'weapon')
                ->pluck('proficiency_name')
                ->values()->all(),
            'tool_proficiencies' => $this->proficiencies
                ->where('proficiency_type', 'tool')
                ->pluck('proficiency_name')
                ->values()->all(),
            'skill_proficiencies' => $this->proficiencies
                ->where('proficiency_type', 'skill')
                ->pluck('proficiency_name')
                ->values()->all(),
            // Optional features (invocations, maneuvers, etc.)
            'has_optional_features' => $this->optionalFeatures->isNotEmpty(),
            'optional_feature_count' => $this->optionalFeatures->count(),
            'optional_feature_types' => $this->optionalFeatures
                ->pluck('feature_type')
                ->map(fn ($type) => $type?->value)
                ->filter()
                ->unique()
                ->values()
                ->all(),
        ];
    }

    public function searchableWith(): array
    {
        return [
            'sources.source',
            'parentClass',
            'spellcastingAbility',
            'tags',
            'proficiencies.skill',
            'proficiencies.proficiencyType',
            'spells',
            'optionalFeatures',
        ];
    }

    public function searchableWithCount(): array
    {
        return ['spells'];
    }

    public function searchableAs(): string
    {
        $prefix = config('scout.prefix');

        return $prefix.'classes';
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
                'hit_die',
                'archetype',
                'primary_ability',
                'spellcasting_ability',
                'is_spellcaster',
                'source_codes',
                'is_subclass',
                'is_base_class',
                'parent_class_name',
                'tag_slugs',
                // Phase 3: Spell counts
                'has_spells',
                'spell_count',
                'max_spell_level',
                // Phase 4: Proficiencies
                'saving_throw_proficiencies',
                'armor_proficiencies',
                'weapon_proficiencies',
                'tool_proficiencies',
                'skill_proficiencies',
                // Optional features
                'has_optional_features',
                'optional_feature_count',
                'optional_feature_types',
            ],
            'sortableAttributes' => [
                'name',
                'hit_die',
                'spell_count',
            ],
            'searchableAttributes' => [
                'name',
                'description',
                'primary_ability',
                'spellcasting_ability',
                'parent_class_name',
                'sources',
                'saving_throw_proficiencies',
                'armor_proficiencies',
                'weapon_proficiencies',
                'tool_proficiencies',
                'skill_proficiencies',
            ],
        ];
    }
}
