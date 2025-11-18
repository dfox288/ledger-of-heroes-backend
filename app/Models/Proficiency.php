<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Proficiency extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'reference_type',
        'reference_id',
        'proficiency_type',
        'skill_id',
        'item_id',
        'ability_score_id',
        'proficiency_name',
    ];

    protected $casts = [
        'reference_id' => 'integer',
        'skill_id' => 'integer',
        'item_id' => 'integer',
        'ability_score_id' => 'integer',
    ];

    // Polymorphic relationship
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    // Relationships to lookup tables
    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function abilityScore(): BelongsTo
    {
        return $this->belongsTo(AbilityScore::class);
    }
}
