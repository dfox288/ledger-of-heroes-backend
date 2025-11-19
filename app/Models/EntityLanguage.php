<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EntityLanguage extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'reference_type',
        'reference_id',
        'language_id',
        'is_choice',
    ];

    protected $casts = [
        'reference_id' => 'integer',
        'language_id' => 'integer',
        'is_choice' => 'boolean',
    ];

    // Polymorphic relationship to parent entity (Race, Background, Class, etc.)
    public function entity(): MorphTo
    {
        return $this->morphTo(null, 'reference_type', 'reference_id');
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }
}
