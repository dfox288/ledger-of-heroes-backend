<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProficiencyType extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'category',
        'subcategory',
        'item_id',
    ];

    protected $casts = [
        'item_id' => 'integer',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function proficiencies(): HasMany
    {
        return $this->hasMany(Proficiency::class);
    }

    /**
     * Get all classes that have this proficiency type
     */
    public function classes()
    {
        return CharacterClass::whereHas('proficiencies', function ($query) {
            $query->where('proficiency_type_id', $this->id);
        })->orderBy('name');
    }

    /**
     * Get all races that have this proficiency type
     */
    public function races()
    {
        return Race::whereHas('proficiencies', function ($query) {
            $query->where('proficiency_type_id', $this->id);
        })->orderBy('name');
    }

    /**
     * Get all backgrounds that have this proficiency type
     */
    public function backgrounds()
    {
        return Background::whereHas('proficiencies', function ($query) {
            $query->where('proficiency_type_id', $this->id);
        })->orderBy('name');
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeBySubcategory($query, string $subcategory)
    {
        return $query->where('subcategory', $subcategory);
    }
}
