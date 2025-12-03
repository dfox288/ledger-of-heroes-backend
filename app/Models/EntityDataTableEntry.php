<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * EntityDataTableEntry - Row entry for a data table.
 *
 * Table: entity_data_table_entries
 * Parent: EntityDataTable (belongs to)
 *
 * Represents a single row in a data table (roll result, level progression entry, etc.).
 */
class EntityDataTableEntry extends BaseModel
{
    protected $fillable = [
        'entity_data_table_id',
        'roll_min',
        'roll_max',
        'result_text',
        'level',
        'sort_order',
        'resource_cost',
    ];

    protected $casts = [
        'entity_data_table_id' => 'integer',
        'roll_min' => 'integer',
        'roll_max' => 'integer',
        'level' => 'integer',
        'sort_order' => 'integer',
        'resource_cost' => 'integer',
    ];

    // Relationship
    public function entityDataTable(): BelongsTo
    {
        return $this->belongsTo(EntityDataTable::class);
    }

    // Alias for backwards compatibility
    public function dataTable(): BelongsTo
    {
        return $this->entityDataTable();
    }
}
