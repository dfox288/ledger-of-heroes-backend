<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Background extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'slug',
        'name',
    ];

    // Polymorphic relationships
    public function traits(): MorphMany
    {
        return $this->morphMany(CharacterTrait::class, 'reference');
    }

    public function proficiencies(): MorphMany
    {
        return $this->morphMany(Proficiency::class, 'reference');
    }

    public function sources(): MorphMany
    {
        return $this->morphMany(EntitySource::class, 'reference', 'reference_type', 'reference_id');
    }

    public function equipment(): MorphMany
    {
        return $this->morphMany(EntityItem::class, 'reference');
    }

    // Scopes for API filtering
    public function scopeSearch($query, $searchTerm)
    {
        return $query->where('name', 'LIKE', "%{$searchTerm}%")
            ->orWhereHas('traits', fn ($q) => $q->where('text', 'LIKE', "%{$searchTerm}%"));
    }
}
