<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Language extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'slug',
        'script',
        'typical_speakers',
        'description',
    ];

    public function entityLanguages(): HasMany
    {
        return $this->hasMany(EntityLanguage::class);
    }
}
