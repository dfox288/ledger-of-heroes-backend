<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Laravel\Scout\Searchable;
use Spatie\Tags\HasTags;

class Spell extends Model
{
    use HasFactory, HasTags, Searchable;

    public $timestamps = false;

    protected $fillable = [
        'slug',
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

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(CharacterClass::class, 'class_spells', 'spell_id', 'class_id');
    }

    public function effects(): HasMany
    {
        return $this->hasMany(SpellEffect::class);
    }

    public function sources(): MorphMany
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

    // Scout Search Configuration

    /**
     * Get the indexable data array for the model.
     */
    public function toSearchableArray(): array
    {
        // Load relationships to avoid N+1 queries
        $this->loadMissing(['spellSchool', 'sources.source', 'classes']);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'level' => $this->level,
            'school_name' => $this->spellSchool?->name,
            'school_code' => $this->spellSchool?->code,
            'casting_time' => $this->casting_time,
            'range' => $this->range,
            'components' => $this->components,
            'duration' => $this->duration,
            'concentration' => $this->needs_concentration,
            'ritual' => $this->is_ritual,
            'description' => $this->description,
            'at_higher_levels' => $this->higher_levels,
            'sources' => $this->sources->pluck('source.name')->all(),
            'source_codes' => $this->sources->pluck('source.code')->all(),
            'classes' => $this->classes->pluck('name')->all(),
            'class_slugs' => $this->classes->pluck('slug')->all(),
        ];
    }

    /**
     * Get the relationships that should be eager loaded for search.
     */
    public function searchableWith(): array
    {
        return ['spellSchool', 'sources', 'classes'];
    }

    /**
     * Get the name of the index associated with the model.
     */
    public function searchableAs(): string
    {
        return 'spells';
    }
}
