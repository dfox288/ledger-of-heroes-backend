<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Laravel\Scout\Searchable;
use Spatie\Tags\HasTags;

class Item extends BaseModel
{
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

    public function sources(): MorphMany
    {
        return $this->morphMany(EntitySource::class, 'reference');
    }

    public function proficiencies(): MorphMany
    {
        return $this->morphMany(Proficiency::class, 'reference');
    }

    public function modifiers(): MorphMany
    {
        return $this->morphMany(Modifier::class, 'reference');
    }

    public function randomTables(): MorphMany
    {
        return $this->morphMany(RandomTable::class, 'reference');
    }

    public function prerequisites(): MorphMany
    {
        return $this->morphMany(EntityPrerequisite::class, 'reference');
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
        $this->loadMissing(['itemType', 'sources.source', 'damageType', 'spells', 'tags']);

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
            'sources' => $this->sources->pluck('source.name')->all(),
            'source_codes' => $this->sources->pluck('source.code')->all(),
            // Weapon-specific
            'damage_dice' => $this->damage_dice,
            'damage_type' => $this->damageType?->name,
            'range_normal' => $this->range_normal,
            'range_long' => $this->range_long,
            // Armor-specific
            'armor_class' => $this->armor_class,
            'strength_requirement' => $this->strength_requirement,
            'stealth_disadvantage' => $this->stealth_disadvantage,
            // Spell filtering (similar to Monster)
            'spell_slugs' => $this->spells->pluck('slug')->all(),
            // Tag filtering
            'tag_slugs' => $this->tags->pluck('slug')->all(),
        ];
    }

    /**
     * Get the relationships that should be eager loaded for search.
     */
    public function searchableWith(): array
    {
        return ['itemType', 'sources.source', 'damageType', 'spells'];
    }

    /**
     * Get the name of the index associated with the model.
     */
    public function searchableAs(): string
    {
        return 'items';
    }
}
