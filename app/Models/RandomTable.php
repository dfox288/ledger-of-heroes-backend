<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RandomTable extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'reference_type',
        'reference_id',
        'table_name',
        'dice_type',
        'description',
    ];

    protected $casts = [
        'reference_id' => 'integer',
    ];

    // Polymorphic relationship
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    // Entries relationship
    public function entries(): HasMany
    {
        return $this->hasMany(RandomTableEntry::class)->orderBy('sort_order');
    }
}
