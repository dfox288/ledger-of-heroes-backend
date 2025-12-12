<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CharacterTrait extends BaseModel
{
    protected $table = 'entity_traits';

    protected $fillable = [
        'reference_type',
        'reference_id',
        'name',
        'category',
        'description',
        'attack_data',
        'sort_order',
    ];

    protected $casts = [
        'reference_id' => 'integer',
        'sort_order' => 'integer',
    ];

    // Polymorphic relationship to parent entity (Race, Class, etc.)
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    // Data tables that reference this trait (via reference_type/reference_id)
    public function dataTables(): MorphMany
    {
        return $this->morphMany(EntityDataTable::class, 'reference');
    }
}
