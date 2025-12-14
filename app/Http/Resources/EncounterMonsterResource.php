<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EncounterMonsterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'monster_id' => $this->monster_id,
            'label' => $this->label,
            'current_hp' => $this->current_hp,
            'max_hp' => $this->max_hp,
            'monster' => $this->whenLoaded('monster', fn () => [
                'name' => $this->monster->name,
                'slug' => $this->monster->slug,
                'armor_class' => $this->monster->armor_class,
                'armor_type' => $this->monster->armor_type,
                'hit_points_average' => $this->monster->hit_points_average,
                'hit_dice' => $this->monster->hit_dice,
                'speed_walk' => $this->monster->speed_walk,
                'speed_fly' => $this->monster->speed_fly,
                'speed_swim' => $this->monster->speed_swim,
                'speed_burrow' => $this->monster->speed_burrow,
                'speed_climb' => $this->monster->speed_climb,
                'challenge_rating' => $this->monster->challenge_rating,
                // Actions are pre-filtered (reactions excluded) at query level
                'actions' => MonsterActionResource::collection(
                    $this->monster->relationLoaded('actions') ? $this->monster->actions : []
                ),
            ]),
        ];
    }
}
