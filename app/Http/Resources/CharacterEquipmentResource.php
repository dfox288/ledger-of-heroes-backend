<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\CharacterEquipment
 */
class CharacterEquipmentResource extends JsonResource
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
            'item' => [
                'id' => $this->item->id,
                'name' => $this->item->name,
                'slug' => $this->item->slug,
                'item_type' => $this->item->itemType?->name,
                'armor_class' => $this->item->armor_class,
                'damage_dice' => $this->item->damage_dice,
                'weight' => $this->item->weight,
            ],
            'quantity' => $this->quantity,
            'equipped' => $this->equipped,
            'location' => $this->location,
        ];
    }
}
