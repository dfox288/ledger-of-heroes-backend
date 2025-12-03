<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentChoiceItem extends BaseModel
{
    protected $fillable = [
        'entity_item_id',
        'proficiency_type_id',
        'item_id',
        'quantity',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'sort_order' => 'integer',
    ];

    public function entityItem(): BelongsTo
    {
        return $this->belongsTo(EntityItem::class);
    }

    public function proficiencyType(): BelongsTo
    {
        return $this->belongsTo(ProficiencyType::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
