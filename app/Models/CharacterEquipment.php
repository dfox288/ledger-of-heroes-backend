<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CharacterEquipment extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'character_equipment';

    protected $fillable = [
        'character_id',
        'item_id',
        'quantity',
        'equipped',
        'location',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'equipped' => 'boolean',
        'created_at' => 'datetime',
    ];

    // Relationships

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    // Helper methods

    public function isEquipped(): bool
    {
        return $this->equipped;
    }

    public function equip(): void
    {
        $this->update([
            'equipped' => true,
            'location' => 'equipped',
        ]);
    }

    public function unequip(): void
    {
        $this->update([
            'equipped' => false,
            'location' => 'backpack',
        ]);
    }

    // Scopes

    /**
     * @param  Builder<CharacterEquipment>  $query
     * @return Builder<CharacterEquipment>
     */
    public function scopeEquipped(Builder $query): Builder
    {
        return $query->where('equipped', true);
    }

    /**
     * @param  Builder<CharacterEquipment>  $query
     * @return Builder<CharacterEquipment>
     */
    public function scopeArmor(Builder $query): Builder
    {
        return $query->whereHas('item', fn ($q) => $q->whereIn('item_type_id', [4, 5, 6]));
    }

    /**
     * @param  Builder<CharacterEquipment>  $query
     * @return Builder<CharacterEquipment>
     */
    public function scopeShields(Builder $query): Builder
    {
        return $query->whereHas('item', fn ($q) => $q->where('item_type_id', 7));
    }

    /**
     * @param  Builder<CharacterEquipment>  $query
     * @return Builder<CharacterEquipment>
     */
    public function scopeWeapons(Builder $query): Builder
    {
        return $query->whereHas('item', fn ($q) => $q->whereIn('item_type_id', [2, 3]));
    }

    // Type checks

    public function isArmor(): bool
    {
        return in_array($this->item->item_type_id, [4, 5, 6]);
    }

    public function isShield(): bool
    {
        return $this->item->item_type_id === 7;
    }

    public function isWeapon(): bool
    {
        return in_array($this->item->item_type_id, [2, 3]);
    }
}
