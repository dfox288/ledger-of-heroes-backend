<?php

namespace App\Models;

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
}
