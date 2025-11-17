<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Proficiency extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_type',
        'reference_id',
        'proficiency_type',
        'name',
    ];

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
