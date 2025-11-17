<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Spell extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'name',
        'level',
        'spell_school_id',
        'casting_time',
        'range',
        'components',
        'material_components',
        'duration',
        'needs_concentration',
        'is_ritual',
        'description',
        'higher_levels',
        'source_id',
        'source_pages',
    ];

    protected $casts = [
        'level' => 'integer',
        'needs_concentration' => 'boolean',
        'is_ritual' => 'boolean',
    ];

    // Relationships
    public function spellSchool(): BelongsTo
    {
        return $this->belongsTo(SpellSchool::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(ClassModel::class, 'class_spells');
    }

    public function effects(): HasMany
    {
        return $this->hasMany(SpellEffect::class);
    }

    // Scopes for API filtering
    public function scopeLevel($query, $level)
    {
        return $query->where('level', $level);
    }

    public function scopeSchool($query, $schoolId)
    {
        return $query->where('spell_school_id', $schoolId);
    }

    public function scopeConcentration($query, $needsConcentration)
    {
        return $query->where('needs_concentration', $needsConcentration);
    }

    public function scopeRitual($query, $isRitual)
    {
        return $query->where('is_ritual', $isRitual);
    }

    public function scopeSearch($query, $searchTerm)
    {
        return $query->whereRaw(
            "MATCH(name, description) AGAINST(? IN NATURAL LANGUAGE MODE)",
            [$searchTerm]
        );
    }
}
