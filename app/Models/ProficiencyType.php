<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProficiencyType extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'category',
        'item_id',
    ];

    protected $casts = [
        'item_id' => 'integer',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}
