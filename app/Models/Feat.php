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
}
