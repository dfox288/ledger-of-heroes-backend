<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EntityCondition extends BaseModel
{
    protected $fillable = [
        'reference_type',
        'reference_id',
        'condition_id',
        'effect_type',
        'description',
    ];

    protected $casts = [
        'reference_id' => 'integer',
        'condition_id' => 'integer',
    ];

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function condition(): BelongsTo
    {
        return $this->belongsTo(Condition::class);
    }
}
