<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EntityItem extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'reference_type',
        'reference_id',
        'item_id',
        'quantity',
        'is_choice',
        'choice_description',
        'description',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'is_choice' => 'boolean',
    ];

    public function reference(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'reference_type', 'reference_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
