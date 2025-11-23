<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonsterSpellcasting extends BaseModel
{
    protected $table = 'monster_spellcasting';

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
