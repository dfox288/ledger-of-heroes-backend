<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EncounterPreset extends Model
{
    use HasFactory;

    protected $fillable = [
        'party_id',
        'name',
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function monsters(): BelongsToMany
    {
        return $this->belongsToMany(Monster::class, 'encounter_preset_monsters')
            ->withPivot('quantity');
    }
}
