<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

class Feat extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'source_book_id',
        'source_page',
    ];

    protected $casts = [
        'source_page' => 'integer',
    ];

    public function sourceBook(): BelongsTo
    {
        return $this->belongsTo(SourceBook::class, 'source_book_id');
    }

    public function modifiers(): MorphMany
    {
        return $this->morphMany(Modifier::class, 'reference');
    }

    public function generateSlug(): void
    {
        $this->slug = Str::slug($this->name);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($feat) {
            if (empty($feat->slug)) {
                $feat->generateSlug();
            }
        });
    }
}
