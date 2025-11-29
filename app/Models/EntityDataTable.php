<?php

namespace App\Models;

use App\Enums\DataTableType;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EntityDataTable extends BaseModel
{
    protected $fillable = [
        'reference_type',
        'reference_id',
        'table_name',
        'dice_type',
        'table_type',
        'description',
    ];

    protected $casts = [
        'reference_id' => 'integer',
        'table_type' => DataTableType::class,
    ];

    protected $attributes = [
        'table_type' => 'random',
    ];

    // Polymorphic relationship
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    // Entries relationship
    public function entries(): HasMany
    {
        return $this->hasMany(EntityDataTableEntry::class)->orderBy('sort_order');
    }
}
