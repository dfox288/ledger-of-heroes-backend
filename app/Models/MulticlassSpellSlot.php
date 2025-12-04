<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MulticlassSpellSlot extends Model
{
    protected $table = 'multiclass_spell_slots';

    protected $primaryKey = 'caster_level';

    public $incrementing = false;

    public $timestamps = false;

    protected $casts = [
        'caster_level' => 'integer',
        'slots_1st' => 'integer',
        'slots_2nd' => 'integer',
        'slots_3rd' => 'integer',
        'slots_4th' => 'integer',
        'slots_5th' => 'integer',
        'slots_6th' => 'integer',
        'slots_7th' => 'integer',
        'slots_8th' => 'integer',
        'slots_9th' => 'integer',
    ];

    /**
     * Get spell slots for a given caster level.
     * Returns null for level 0, caps at level 20.
     */
    public static function forCasterLevel(int $level): ?self
    {
        if ($level < 1) {
            return null;
        }

        return self::find(min($level, 20));
    }

    /**
     * Get slots as an array indexed by spell level.
     *
     * @return array<string, int>
     */
    public function toSlotsArray(): array
    {
        return [
            '1st' => $this->slots_1st,
            '2nd' => $this->slots_2nd,
            '3rd' => $this->slots_3rd,
            '4th' => $this->slots_4th,
            '5th' => $this->slots_5th,
            '6th' => $this->slots_6th,
            '7th' => $this->slots_7th,
            '8th' => $this->slots_8th,
            '9th' => $this->slots_9th,
        ];
    }
}
