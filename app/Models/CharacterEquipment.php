<?php

namespace App\Models;

use App\Enums\ItemTypeCode;
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
        'item_slug',
        'custom_name',
        'custom_description',
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
        return $this->belongsTo(Item::class, 'item_slug', 'full_slug');
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
        return $query->whereHas('item.itemType', fn ($q) => $q->whereIn('code', ItemTypeCode::armorCodes()));
    }

    /**
     * @param  Builder<CharacterEquipment>  $query
     * @return Builder<CharacterEquipment>
     */
    public function scopeShields(Builder $query): Builder
    {
        return $query->whereHas('item.itemType', fn ($q) => $q->where('code', ItemTypeCode::SHIELD->value));
    }

    /**
     * @param  Builder<CharacterEquipment>  $query
     * @return Builder<CharacterEquipment>
     */
    public function scopeWeapons(Builder $query): Builder
    {
        return $query->whereHas('item.itemType', fn ($q) => $q->whereIn('code', ItemTypeCode::weaponCodes()));
    }

    // Type checks

    public function isArmor(): bool
    {
        return in_array($this->item->itemType?->code, ItemTypeCode::armorCodes());
    }

    public function isShield(): bool
    {
        return $this->item->itemType?->code === ItemTypeCode::SHIELD->value;
    }

    public function isWeapon(): bool
    {
        return in_array($this->item->itemType?->code, ItemTypeCode::weaponCodes());
    }

    /**
     * Check if this item can be equipped.
     * Custom items cannot be equipped.
     */
    public function isEquippable(): bool
    {
        if ($this->isCustomItem()) {
            return false;
        }

        return in_array($this->item->itemType?->code, ItemTypeCode::equippableCodes());
    }

    /**
     * Check if this is a custom/freetext item (not from database).
     */
    public function isCustomItem(): bool
    {
        return $this->item_slug === null && $this->custom_name !== null;
    }
}
