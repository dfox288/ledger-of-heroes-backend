<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Modifier extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'reference_type',
        'reference_id',
        'modifier_category',
        'ability_score_id',
        'skill_id',
        'damage_type_id',
        'value',
        'condition',
    ];

    protected $casts = [
        'reference_id' => 'integer',
        'ability_score_id' => 'integer',
        'skill_id' => 'integer',
        'damage_type_id' => 'integer',
    ];

    // Polymorphic relationship
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    // Relationships to lookup tables
    public function abilityScore(): BelongsTo
    {
        return $this->belongsTo(AbilityScore::class);
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }

    public function damageType(): BelongsTo
    {
        return $this->belongsTo(DamageType::class);
    }
}
