<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MonsterSpellcastingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'spell_slots' => $this->spell_slots,
            'spellcasting_ability' => $this->spellcasting_ability,
            'spell_save_dc' => $this->spell_save_dc,
            'spell_attack_bonus' => $this->spell_attack_bonus,
        ];
    }
}
