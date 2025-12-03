<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EntityItem extends BaseModel
{
    protected $fillable = [
        'reference_type',
        'reference_id',
        'item_id',
        'quantity',
        'is_choice',
        'choice_group',
        'choice_option',
        'choice_description',
        'proficiency_subcategory',
        'description',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'is_choice' => 'boolean',
        'choice_option' => 'integer',
    ];

    public function reference(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'reference_type', 'reference_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function choiceItems(): HasMany
    {
        return $this->hasMany(EquipmentChoiceItem::class)->orderBy('sort_order');
    }
}
