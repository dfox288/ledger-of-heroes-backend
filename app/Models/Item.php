<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'item_type_id',
        'rarity_id',
        'weight_lbs',
        'value_gp',
        'description',
        'attunement_required',
        'attunement_requirements',
        'source_book_id',
        'source_page',
    ];

    protected $casts = [
        'weight_lbs' => 'decimal:2',
        'value_gp' => 'decimal:2',
        'attunement_required' => 'boolean',
        'source_page' => 'integer',
    ];

    public function itemType(): BelongsTo
    {
        return $this->belongsTo(ItemType::class, 'item_type_id');
    }

    public function rarity(): BelongsTo
    {
        return $this->belongsTo(ItemRarity::class, 'rarity_id');
    }

    public function sourceBook(): BelongsTo
    {
        return $this->belongsTo(SourceBook::class, 'source_book_id');
    }

    public function generateSlug(): void
    {
        $this->slug = Str::slug($this->name);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($item) {
            if (empty($item->slug)) {
                $item->generateSlug();
            }
        });
    }
}
