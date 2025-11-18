<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

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
        return $this->belongsToMany(CharacterClass::class, 'class_spells', 'spell_id', 'class_id');
    }

    public function effects(): HasMany
    {
        return $this->hasMany(SpellEffect::class);
    }

    public function entitySources(): MorphMany
    {
        return $this->morphMany(EntitySource::class, 'reference', 'reference_type', 'reference_id');
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
        $driver = $query->getConnection()->getDriverName();

        if ($driver === 'mysql') {
            // Search name with LIKE (prioritizes name matches)
            return $query->where('name', 'LIKE', "%{$searchTerm}%");
        }

        // Fallback to LIKE search for other databases (e.g., SQLite for testing)
        return $query->where(function ($q) use ($searchTerm) {
            $q->where('name', 'LIKE', "%{$searchTerm}%")
              ->orWhere('description', 'LIKE', "%{$searchTerm}%");
        });
    }
}
