<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'item_type_id',
        'rarity',
        'requires_attunement',
        'cost_cp',
        'weight',
        'damage_dice',
        'versatile_damage',
        'damage_type_id',
        'armor_class',
        'strength_requirement',
        'stealth_disadvantage',
        'weapon_range',
        'description',
    ];

    protected $casts = [
        'requires_attunement' => 'boolean',
        'cost_cp' => 'integer',
        'weight' => 'decimal:2',
        'armor_class' => 'integer',
        'strength_requirement' => 'integer',
        'stealth_disadvantage' => 'boolean',
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
        return $this->belongsToMany(ItemProperty::class, 'item_property');
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
}
