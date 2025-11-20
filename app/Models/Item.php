<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Laravel\Scout\Searchable;

class Item extends Model
{
    use HasFactory;
    use Searchable;

    protected $fillable = [
        'name',
        'slug',
        'item_type_id',
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
        $this->loadMissing(['itemType', 'sources.source', 'damageType']);

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
        ];
    }

    /**
     * Get the relationships that should be eager loaded for search.
     */
    public function searchableWith(): array
    {
        return ['itemType', 'sources.source', 'damageType'];
    }

    /**
     * Get the name of the index associated with the model.
     */
    public function searchableAs(): string
    {
        return 'items';
    }
}
