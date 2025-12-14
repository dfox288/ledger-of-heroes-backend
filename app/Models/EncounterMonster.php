<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EncounterMonster extends Model
{
    use HasFactory;

    protected $fillable = [
        'party_id',
        'monster_id',
        'label',
        'current_hp',
        'max_hp',
    ];

    protected $casts = [
        'current_hp' => 'integer',
        'max_hp' => 'integer',
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function monster(): BelongsTo
    {
        return $this->belongsTo(Monster::class);
    }
}
