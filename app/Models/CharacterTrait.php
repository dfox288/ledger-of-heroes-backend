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
        'attack_data',
        'sort_order',
        'entity_data_table_id',
    ];

    protected $casts = [
        'reference_id' => 'integer',
        'sort_order' => 'integer',
        'entity_data_table_id' => 'integer',
    ];

    // Polymorphic relationship to parent entity (Race, Class, etc.)
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    // Bidirectional relationship to data tables
    // A trait can have many data tables referencing it
    public function dataTables(): MorphMany
    {
        return $this->morphMany(EntityDataTable::class, 'reference');
    }

    // A trait can also be linked to a single data table via entity_data_table_id
    public function dataTable(): BelongsTo
    {
        return $this->belongsTo(EntityDataTable::class, 'entity_data_table_id');
    }
}
