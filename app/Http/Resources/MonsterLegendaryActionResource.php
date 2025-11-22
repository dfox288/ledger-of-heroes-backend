<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MonsterLegendaryActionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'action_cost' => $this->action_cost,
            'is_lair_action' => $this->is_lair_action,
            'attack_data' => $this->attack_data,
            'recharge' => $this->recharge,
            'sort_order' => $this->sort_order,
        ];
    }
}
