<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

class Race extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'size_id',
        'speed',
        'source_book_id',
        'source_page',
    ];

    protected $casts = [
        'speed' => 'integer',
        'source_page' => 'integer',
    ];

    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class, 'size_id');
    }

    public function sourceBook(): BelongsTo
    {
        return $this->belongsTo(SourceBook::class, 'source_book_id');
    }

    public function traits(): MorphMany
    {
        return $this->morphMany(CharacterTrait::class, 'reference');
    }

    public function modifiers(): MorphMany
    {
        return $this->morphMany(Modifier::class, 'reference');
    }

    public function proficiencies(): MorphMany
    {
        return $this->morphMany(Proficiency::class, 'reference');
    }

    public function generateSlug(): void
    {
        $this->slug = Str::slug($this->name);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($race) {
            if (empty($race->slug)) {
                $race->generateSlug();
            }
        });
    }
}
