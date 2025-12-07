<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CharacterProficiency extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'character_id',
        'proficiency_type_slug',
        'skill_slug',
        'source',
        'choice_group',
        'expertise',
    ];

    protected $casts = [
        'expertise' => 'boolean',
        'created_at' => 'datetime',
    ];

    // Relationships

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function proficiencyType(): BelongsTo
    {
        return $this->belongsTo(ProficiencyType::class, 'proficiency_type_slug', 'full_slug');
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class, 'skill_slug', 'full_slug');
    }

    // Helper methods

    public function isSkillProficiency(): bool
    {
        return $this->skill_slug !== null;
    }

    public function hasExpertise(): bool
    {
        return $this->expertise;
    }
}
