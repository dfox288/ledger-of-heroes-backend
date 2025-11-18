<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'item_type_id' => $this->item_type_id,
            'rarity' => $this->rarity,
            'requires_attunement' => $this->requires_attunement,
            'is_magic' => $this->is_magic,
            'cost_cp' => $this->cost_cp,
            'weight' => $this->weight,
            'damage_dice' => $this->damage_dice,
            'versatile_damage' => $this->versatile_damage,
            'damage_type_id' => $this->damage_type_id,
            'range_normal' => $this->range_normal,
            'range_long' => $this->range_long,
            'armor_class' => $this->armor_class,
            'strength_requirement' => $this->strength_requirement,
            'stealth_disadvantage' => $this->stealth_disadvantage,
            'description' => $this->description,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Relationships
            'item_type' => ItemTypeResource::make($this->whenLoaded('itemType')),
            'damage_type' => DamageTypeResource::make($this->whenLoaded('damageType')),
            'properties' => ItemPropertyResource::collection($this->whenLoaded('properties')),
            'abilities' => ItemAbilityResource::collection($this->whenLoaded('abilities')),
            'sources' => EntitySourceResource::collection($this->whenLoaded('sources')),
            'proficiencies' => ProficiencyResource::collection($this->whenLoaded('proficiencies')),
            'modifiers' => ModifierResource::collection($this->whenLoaded('modifiers')),
        ];
    }
}
