<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MonsterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'sort_name' => $this->sort_name,
            'size' => new SizeResource($this->whenLoaded('size')),
            'type' => $this->type,
            'alignment' => $this->alignment,
            'is_npc' => $this->is_npc,
            'armor_class' => $this->armor_class,
            'armor_type' => $this->armor_type,
            'hit_points_average' => $this->hit_points_average,
            'hit_dice' => $this->hit_dice,
            'speed_walk' => $this->speed_walk,
            'speed_fly' => $this->speed_fly,
            'speed_swim' => $this->speed_swim,
            'speed_burrow' => $this->speed_burrow,
            'speed_climb' => $this->speed_climb,
            'can_hover' => $this->can_hover,
            'strength' => $this->strength,
            'dexterity' => $this->dexterity,
            'constitution' => $this->constitution,
            'intelligence' => $this->intelligence,
            'wisdom' => $this->wisdom,
            'charisma' => $this->charisma,
            'challenge_rating' => $this->challenge_rating,
            'experience_points' => $this->experience_points,
            'proficiency_bonus' => $this->proficiency_bonus,
            'is_legendary' => $this->is_legendary,
            'passive_perception' => $this->passive_perception,
            'description' => $this->description,
            'traits' => MonsterTraitResource::collection($this->whenLoaded('traits')),
            'actions' => MonsterActionResource::collection($this->whenLoaded('actions')),
            'legendary_actions' => MonsterLegendaryActionResource::collection($this->whenLoaded('legendaryActions')),
            'spells' => EntitySpellResource::collection($this->whenLoaded('entitySpells')),
            'modifiers' => ModifierResource::collection($this->whenLoaded('modifiers')),
            'conditions' => EntityConditionResource::collection($this->whenLoaded('conditions')),
            'sources' => EntitySourceResource::collection($this->whenLoaded('sources')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
        ];
    }
}
