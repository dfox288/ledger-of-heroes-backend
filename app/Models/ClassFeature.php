<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassFeature extends BaseModel
{
    protected $table = 'class_features';

    protected $fillable = [
        'class_id',
        'level',
        'feature_name',
        'is_optional',
        'description',
        'sort_order',
    ];

    protected $casts = [
        'class_id' => 'integer',
        'level' => 'integer',
        'is_optional' => 'boolean',
        'sort_order' => 'integer',
    ];

    // Relationships
    public function characterClass(): BelongsTo
    {
        return $this->belongsTo(CharacterClass::class, 'class_id');
    }
}
