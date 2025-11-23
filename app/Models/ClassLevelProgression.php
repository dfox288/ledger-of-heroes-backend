<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassLevelProgression extends BaseModel
{
    protected $table = 'class_level_progression';

    protected $fillable = [
        'class_id',
        'level',
        'cantrips_known',
        'spell_slots_1st',
        'spell_slots_2nd',
        'spell_slots_3rd',
        'spell_slots_4th',
        'spell_slots_5th',
        'spell_slots_6th',
        'spell_slots_7th',
        'spell_slots_8th',
        'spell_slots_9th',
        'spells_known',
    ];

    protected $casts = [
        'class_id' => 'integer',
        'level' => 'integer',
        'cantrips_known' => 'integer',
        'spell_slots_1st' => 'integer',
        'spell_slots_2nd' => 'integer',
        'spell_slots_3rd' => 'integer',
        'spell_slots_4th' => 'integer',
        'spell_slots_5th' => 'integer',
        'spell_slots_6th' => 'integer',
        'spell_slots_7th' => 'integer',
        'spell_slots_8th' => 'integer',
        'spell_slots_9th' => 'integer',
        'spells_known' => 'integer',
    ];

    // Relationships
    public function characterClass(): BelongsTo
    {
        return $this->belongsTo(CharacterClass::class, 'class_id');
    }
}
