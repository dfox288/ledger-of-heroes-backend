<?php

namespace App\Models;

use App\Models\Concerns\HasDataTables;
use App\Models\Concerns\HasModifiers;
use App\Models\Concerns\HasPrerequisites;
use App\Models\Concerns\HasProficiencies;
use App\Models\Concerns\HasSearchableHelpers;
use App\Models\Concerns\HasSources;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Laravel\Scout\Searchable;
use Spatie\Tags\HasTags;

class Item extends BaseModel
{
    use HasDataTables, HasModifiers, HasPrerequisites, HasProficiencies, HasSearchableHelpers, HasSources;
    use HasTags, Searchable;

    protected $fillable = [
        'name',
        'slug',
        'item_type_id',
        'detail',
        'rarity',
        'requires_attunement',
        'is_magic',
        'cost_cp',
        'weight',
        'damage_dice',
        'versatile_damage',
        'damage_type_id',
        'range_normal',
        'range_long',
        'armor_class',
        'strength_requirement',
        'stealth_disadvantage',
        'description',
        'charges_max',
        'recharge_formula',
        'recharge_timing',
    ];

    protected $casts = [
        'requires_attunement' => 'boolean',
        'is_magic' => 'boolean',
        'cost_cp' => 'integer',
        'weight' => 'decimal:2',
        'armor_class' => 'integer',
        'strength_requirement' => 'integer',
        'stealth_disadvantage' => 'boolean',
        'range_normal' => 'integer',
        'range_long' => 'integer',
        // charges_max is string to support both static values ("7") and dice formulas ("1d4-1")
    ];

    // Relationships

    public function itemType(): BelongsTo
    {
        return $this->belongsTo(ItemType::class);
    }

    public function damageType(): BelongsTo
    {
        return $this->belongsTo(DamageType::class);
    }

    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(ItemProperty::class, 'item_property', 'item_id', 'property_id');
    }

    public function abilities(): HasMany
    {
        return $this->hasMany(ItemAbility::class);
    }

    public function spells(): MorphToMany
    {
        return $this->morphToMany(
            Spell::class,
            'reference',
            'entity_spells',
            'reference_id',
            'spell_id'
        )->withPivot([
            'charges_cost_min',
            'charges_cost_max',
            'charges_cost_formula',
            'ability_score_id',
            'level_requirement',
            'usage_limit',
            'is_cantrip',
        ]);
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
            ->withPivot('dc', 'save_effect', 'is_initial_save', 'save_modifier')
            ->withTimestamps();
    }

    // =========================================================================
    // Computed Accessors
    // =========================================================================

    /**
     * Get the proficiency category for weapons (simple/martial + melee/ranged).
     *
     * Returns: simple_melee, martial_melee, simple_ranged, martial_ranged, or null for non-weapons.
     */
    public function getProficiencyCategoryAttribute(): ?string
    {
        $this->loadMissing(['itemType', 'properties']);

        $typeCode = $this->itemType?->code;

        // Only weapons have proficiency categories
        if (! in_array($typeCode, ['M', 'R'])) {
            return null;
        }

        $isMartial = $this->properties->contains('code', 'M');
        $attackType = $typeCode === 'M' ? 'melee' : 'ranged';
        $proficiencyType = $isMartial ? 'martial' : 'simple';

        return "{$proficiencyType}_{$attackType}";
    }

    /**
     * Get the magic bonus (+1/+2/+3) from modifiers.
     *
     * Checks weapon_attack modifier for weapons, ac_magic for armor/shields.
     */
    public function getMagicBonusAttribute(): ?int
    {
        $this->loadMissing('modifiers');

        // Check weapon_attack first (for weapons)
        $weaponBonus = $this->modifiers
            ->where('modifier_category', 'weapon_attack')
            ->first();

        if ($weaponBonus) {
            return (int) $weaponBonus->value;
        }

        // Check ac_magic (for armor/shields)
        $acBonus = $this->modifiers
            ->where('modifier_category', 'ac_magic')
            ->first();

        if ($acBonus) {
            return (int) $acBonus->value;
        }

        return null;
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    /**
     * Scope: Filter by minimum strength requirement
     * Usage: Item::whereMinStrength(15)->get()
     */
    public function scopeWhereMinStrength($query, int $minStrength)
    {
        // Support both old column and new prerequisite system
        return $query->where(function ($q) use ($minStrength) {
            $q->where('strength_requirement', '>=', $minStrength)
                ->orWhereHas('prerequisites', function ($prereqQuery) use ($minStrength) {
                    $prereqQuery->where('prerequisite_type', AbilityScore::class)
                        ->whereHas('prerequisite', function ($abilityQuery) {
                            $abilityQuery->where('code', 'STR');
                        })
                        ->where('minimum_value', '>=', $minStrength);
                });
        });
    }

    /**
     * Scope: Filter by any prerequisite
     * Usage: Item::hasPrerequisites()->get()
     */
    public function scopeHasPrerequisites($query)
    {
        return $query->where(function ($q) {
            $q->whereNotNull('strength_requirement')
                ->orHas('prerequisites');
        });
    }

    /**
     * Get the indexable data array for the model.
     */
    public function toSearchableArray(): array
    {
        // Load relationships to avoid N+1 queries
        $this->loadMissing([
            'itemType',
            'sources.source',
            'damageType',
            'spells',
            'tags',
            'properties',
            'modifiers',
            'proficiencies.proficiencyType',
            'savingThrows',
        ]);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'type_name' => $this->itemType?->name,
            'type_code' => $this->itemType?->code,
            'description' => $this->description,
            'rarity' => $this->rarity,
            'requires_attunement' => $this->requires_attunement,
            'is_magic' => $this->is_magic,
            'weight' => $this->weight,
            'cost_cp' => $this->cost_cp,
            'sources' => $this->getSearchableSourceNames(),
            'source_codes' => $this->getSearchableSourceCodes(),
            // Weapon-specific
            'damage_dice' => $this->damage_dice,
            'versatile_damage' => $this->versatile_damage,
            'damage_type' => $this->damageType?->name,
            'range_normal' => $this->range_normal,
            'range_long' => $this->range_long,
            // Armor-specific
            'armor_class' => $this->armor_class,
            'strength_requirement' => $this->strength_requirement,
            'stealth_disadvantage' => $this->stealth_disadvantage,
            // Charge mechanics (magic items)
            'charges_max' => $this->charges_max,
            'has_charges' => $this->charges_max !== null,
            'recharge_timing' => $this->recharge_timing,
            'recharge_formula' => $this->recharge_formula,
            // Spell filtering (similar to Monster)
            'spell_slugs' => $this->spells->pluck('slug')->all(),
            // Tag filtering
            'tag_slugs' => $this->getSearchableTagSlugs(),
            // Array fields (Phase 4)
            'property_codes' => $this->properties->pluck('code')->all(),
            'modifier_categories' => $this->modifiers->pluck('modifier_category')->unique()->values()->all(),
            'proficiency_names' => $this->proficiencies->pluck('proficiencyType.name')->filter()->unique()->values()->all(),
            'saving_throw_abilities' => $this->savingThrows->pluck('code')->unique()->all(),
            // Prerequisites (boolean for filtering)
            'has_prerequisites' => $this->prerequisites->isNotEmpty() || $this->strength_requirement !== null,
            // Computed fields for filtering
            'proficiency_category' => $this->proficiency_category,
            'magic_bonus' => $this->magic_bonus,
        ];
    }

    /**
     * Get the relationships that should be eager loaded for search.
     */
    public function searchableWith(): array
    {
        return [
            'itemType',
            'sources.source',
            'damageType',
            'spells',
            'properties',
            'modifiers',
            'proficiencies.proficiencyType',
            'savingThrows',
            'prerequisites',
        ];
    }

    /**
     * Get the name of the index associated with the model.
     */
    public function searchableAs(): string
    {
        $prefix = config('scout.prefix');

        return $prefix.'items';
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
                'type_name',
                'type_code',
                'rarity',
                'requires_attunement',
                'is_magic',
                'weight',
                'cost_cp',
                'source_codes',
                'damage_dice',
                'versatile_damage',
                'damage_type',
                'range_normal',
                'range_long',
                'armor_class',
                'strength_requirement',
                'stealth_disadvantage',
                'charges_max',
                'has_charges',
                'recharge_timing',
                'recharge_formula',
                'spell_slugs',
                'tag_slugs',
                'property_codes',
                'modifier_categories',
                'proficiency_names',
                'saving_throw_abilities',
                'has_prerequisites',
                'proficiency_category',
                'magic_bonus',
            ],
            'sortableAttributes' => [
                'name',
                'weight',
                'cost_cp',
                'armor_class',
                'range_normal',
            ],
            'searchableAttributes' => [
                'name',
                'description',
                'type_name',
                'rarity',
                'damage_type',
                'sources',
            ],
        ];
    }
}
