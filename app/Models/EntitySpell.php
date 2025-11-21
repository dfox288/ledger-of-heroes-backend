<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EntitySpell extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'reference_type',
        'reference_id',
        'spell_id',
        'ability_score_id',
        'level_requirement',
        'usage_limit',
        'is_cantrip',
    ];

    protected $casts = [
        'level_requirement' => 'integer',
        'is_cantrip' => 'boolean',
    ];

    public function reference(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'reference_type', 'reference_id');
    }

    public function spell(): BelongsTo
    {
        return $this->belongsTo(Spell::class);
    }

    public function abilityScore(): BelongsTo
    {
        return $this->belongsTo(AbilityScore::class);
    }
}
