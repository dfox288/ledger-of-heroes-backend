<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RandomTableEntry extends Model
{
    use HasFactory;
    public $timestamps = false;

    protected $fillable = [
        'random_table_id',
        'roll_value',
        'result',
        'sort_order',
    ];

    protected $casts = [
        'random_table_id' => 'integer',
        'sort_order' => 'integer',
    ];

    // Relationship
    public function randomTable(): BelongsTo
    {
        return $this->belongsTo(RandomTable::class);
    }
}
