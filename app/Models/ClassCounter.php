<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassCounter extends BaseModel
{
    protected $table = 'class_counters';

    protected $fillable = [
        'class_id',
        'feat_id',
        'level',
        'counter_name',
        'counter_value',
        'reset_timing',
    ];

    protected $casts = [
        'class_id' => 'integer',
        'feat_id' => 'integer',
        'level' => 'integer',
        'counter_value' => 'integer',
    ];

    // Boot method for validation

    protected static function booted(): void
    {
        static::saving(function (ClassCounter $counter) {
            $hasClass = $counter->class_id !== null;
            $hasFeat = $counter->feat_id !== null;

            // XOR: exactly one must be set
            if ($hasClass === $hasFeat) {
                throw new \InvalidArgumentException(
                    'ClassCounter must belong to exactly one of: class_id or feat_id (not both, not neither)'
                );
            }
        });
    }

    // Relationships

    public function characterClass(): BelongsTo
    {
        return $this->belongsTo(CharacterClass::class, 'class_id');
    }

    public function feat(): BelongsTo
    {
        return $this->belongsTo(Feat::class);
    }
}
