<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Feat extends Model
{
    use HasFactory;

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'slug',
        'prerequisites',
        'description',
    ];

    /**
     * Get all sources for this feat (polymorphic).
     */
    public function sources(): MorphMany
    {
        return $this->morphMany(EntitySource::class, 'reference');
    }

    /**
     * Get all modifiers for this feat (polymorphic).
     */
    public function modifiers(): MorphMany
    {
        return $this->morphMany(Modifier::class, 'reference');
    }

    /**
     * Get all proficiencies granted by this feat (polymorphic).
     */
    public function proficiencies(): MorphMany
    {
        return $this->morphMany(Proficiency::class, 'reference');
    }

    /**
     * Get all conditions for this feat (polymorphic).
     */
    public function conditions(): MorphMany
    {
        return $this->morphMany(EntityCondition::class, 'reference');
    }

    /**
     * Get all prerequisites for this feat (polymorphic).
     */
    public function prerequisites(): MorphMany
    {
        return $this->morphMany(EntityPrerequisite::class, 'reference');
    }

    /**
     * Scope a query to search feats.
     */
    public function scopeSearch($query, $searchTerm)
    {
        return $query->where('name', 'LIKE', "%{$searchTerm}%")
            ->orWhere('description', 'LIKE', "%{$searchTerm}%")
            ->orWhere('prerequisites', 'LIKE', "%{$searchTerm}%");
    }
}
