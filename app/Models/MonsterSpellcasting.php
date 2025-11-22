<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonsterSpellcasting extends Model
{
    use HasFactory;

    protected $table = 'monster_spellcasting';

    public $timestamps = false;

    protected $fillable = [
        'monster_id',
        'description',
        'spell_slots',
        'spellcasting_ability',
        'spell_save_dc',
        'spell_attack_bonus',
    ];

    public function monster(): BelongsTo
    {
        return $this->belongsTo(Monster::class);
    }
}
