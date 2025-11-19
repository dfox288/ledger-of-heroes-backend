<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassLevelProgressionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'level' => $this->level,
            'cantrips_known' => $this->cantrips_known,
            'spell_slots_1st' => $this->spell_slots_1st,
            'spell_slots_2nd' => $this->spell_slots_2nd,
            'spell_slots_3rd' => $this->spell_slots_3rd,
            'spell_slots_4th' => $this->spell_slots_4th,
            'spell_slots_5th' => $this->spell_slots_5th,
            'spell_slots_6th' => $this->spell_slots_6th,
            'spell_slots_7th' => $this->spell_slots_7th,
            'spell_slots_8th' => $this->spell_slots_8th,
            'spell_slots_9th' => $this->spell_slots_9th,
        ];
    }
}
