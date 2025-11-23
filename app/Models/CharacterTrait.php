<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'sort_order',
        'random_table_id',
    ];

    protected $casts = [
        'reference_id' => 'integer',
        'sort_order' => 'integer',
        'random_table_id' => 'integer',
    ];

    // Polymorphic relationship to parent entity (Race, Class, etc.)
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    // Bidirectional relationship to random tables
    // A trait can have many random tables referencing it
    public function randomTables(): MorphMany
    {
        return $this->morphMany(RandomTable::class, 'reference');
    }

    // A trait can also be linked to a single random table via random_table_id
    public function randomTable(): BelongsTo
    {
        return $this->belongsTo(RandomTable::class);
    }
}
