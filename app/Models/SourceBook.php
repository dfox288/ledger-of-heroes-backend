<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SourceBook extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'abbreviation',
        'release_date',
        'publisher',
    ];

    protected $casts = [
        'release_date' => 'date',
    ];

    public function spells(): HasMany
    {
        return $this->hasMany(Spell::class, 'source_book_id');
    }
}
