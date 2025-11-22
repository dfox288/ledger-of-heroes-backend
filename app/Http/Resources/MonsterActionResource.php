<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MonsterActionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action_type' => $this->action_type,
            'name' => $this->name,
            'description' => $this->description,
            'attack_data' => $this->attack_data,
            'recharge' => $this->recharge,
            'sort_order' => $this->sort_order,
        ];
    }
}
