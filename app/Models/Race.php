<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Race extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'size_id',
        'speed',
        'description',
        'source_id',
        'source_pages',
        'parent_race_id',
    ];

    protected $casts = [
        'size_id' => 'integer',
        'speed' => 'integer',
        'source_id' => 'integer',
        'parent_race_id' => 'integer',
    ];

    // Relationships
    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Race::class, 'parent_race_id');
    }

    public function subraces(): HasMany
    {
        return $this->hasMany(Race::class, 'parent_race_id');
    }

    public function proficiencies(): MorphMany
    {
        return $this->morphMany(Proficiency::class, 'reference');
    }

    public function traits(): MorphMany
    {
        return $this->morphMany(CharacterTrait::class, 'reference');
    }

    // Scopes for API filtering
    public function scopeSearch($query, $searchTerm)
    {
        // Search name only (learned from spells - don't search description)
        return $query->where('name', 'LIKE', "%{$searchTerm}%");
    }

    public function scopeSize($query, $sizeId)
    {
        return $query->where('size_id', $sizeId);
    }
}
