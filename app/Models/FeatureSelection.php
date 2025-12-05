<?php

namespace App\Models;

use App\Models\Concerns\HasLimitedUses;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks class-granted feature choices for a character.
 *
 * Feature selections include:
 * - Eldritch Invocations (Warlock)
 * - Maneuvers (Fighter - Battle Master)
 * - Metamagic (Sorcerer)
 * - Fighting Styles (Fighter, Paladin, Ranger, etc.)
 * - Artificer Infusions (Artificer)
 * - Runes (Fighter - Rune Knight)
 * - Arcane Shots (Fighter - Arcane Archer)
 * - Elemental Disciplines (Monk - Way of Four Elements)
 */
class FeatureSelection extends Model
{
    use HasFactory;
    use HasLimitedUses;

    protected $table = 'feature_selections';

    protected $fillable = [
        'character_id',
        'optional_feature_id',
        'class_id',
        'subclass_name',
        'level_acquired',
        'uses_remaining',
        'max_uses',
    ];

    protected $casts = [
        'character_id' => 'integer',
        'optional_feature_id' => 'integer',
        'class_id' => 'integer',
        'level_acquired' => 'integer',
        'uses_remaining' => 'integer',
        'max_uses' => 'integer',
    ];

    // Relationships

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function optionalFeature(): BelongsTo
    {
        return $this->belongsTo(OptionalFeature::class);
    }

    public function characterClass(): BelongsTo
    {
        return $this->belongsTo(CharacterClass::class, 'class_id');
    }
}
