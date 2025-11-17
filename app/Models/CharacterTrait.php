<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CharacterTrait extends Model
{
    use HasFactory;

    protected $table = 'traits';

    protected $fillable = [
        'reference_type',
        'reference_id',
        'name',
        'category',
        'description',
    ];

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
