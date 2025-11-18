<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CharacterTrait extends Model
{
    protected $table = 'traits';
    public $timestamps = false;

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

    // Polymorphic relationship
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    // Relationship to random table
    public function randomTable(): BelongsTo
    {
        return $this->belongsTo(RandomTable::class);
    }
}
