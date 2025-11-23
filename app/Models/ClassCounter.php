<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassCounter extends BaseModel
{
    protected $table = 'class_counters';

    protected $fillable = [
        'class_id',
        'level',
        'counter_name',
        'counter_value',
        'reset_timing',
    ];

    protected $casts = [
        'class_id' => 'integer',
        'level' => 'integer',
        'counter_value' => 'integer',
    ];

    // Relationships
    public function characterClass(): BelongsTo
    {
        return $this->belongsTo(CharacterClass::class, 'class_id');
    }
}
