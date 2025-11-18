<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class CharacterClass extends Model
{
    public $timestamps = false;

    protected $table = 'classes';

    protected $fillable = [
        'name',
        'parent_class_id',
        'hit_die',
        'description',
        'primary_ability',
        'spellcasting_ability_id',
        'source_id',
        'source_pages',
    ];

    protected $casts = [
        'hit_die' => 'integer',
        'parent_class_id' => 'integer',
        'spellcasting_ability_id' => 'integer',
        'source_id' => 'integer',
    ];

    // Relationships
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function spellcastingAbility(): BelongsTo
    {
        return $this->belongsTo(AbilityScore::class, 'spellcasting_ability_id');
    }

    public function parentClass(): BelongsTo
    {
        return $this->belongsTo(CharacterClass::class, 'parent_class_id');
    }

    public function subclasses(): HasMany
    {
        return $this->hasMany(CharacterClass::class, 'parent_class_id');
    }

    public function spells(): BelongsToMany
    {
        return $this->belongsToMany(Spell::class, 'class_spells', 'class_id', 'spell_id');
    }

    public function entitySources(): MorphMany
    {
        return $this->morphMany(EntitySource::class, 'entity', 'entity_type', 'entity_id');
    }
}
