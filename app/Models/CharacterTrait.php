<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
    ];

    protected $casts = [
        'reference_id' => 'integer',
        'sort_order' => 'integer',
    ];

    // Polymorphic relationship
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
