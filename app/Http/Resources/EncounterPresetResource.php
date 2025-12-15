<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EncounterPresetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'monsters' => $this->whenLoaded('monsters', fn () => $this->monsters->map(fn ($monster) => [
                'monster_id' => $monster->id,
                'quantity' => $monster->pivot->quantity,
                'monster_name' => $monster->name,
                'challenge_rating' => $monster->challenge_rating,
            ])),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
