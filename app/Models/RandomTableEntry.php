<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RandomTableEntry extends BaseModel
{
    protected $fillable = [
        'random_table_id',
        'roll_min',
        'roll_max',
        'result_text',
        'sort_order',
    ];

    protected $casts = [
        'random_table_id' => 'integer',
        'roll_min' => 'integer',
        'roll_max' => 'integer',
        'sort_order' => 'integer',
    ];

    // Relationship
    public function randomTable(): BelongsTo
    {
        return $this->belongsTo(RandomTable::class);
    }
}
