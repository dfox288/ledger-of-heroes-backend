<?php

namespace App\Http\Resources;

use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Item
 */
class ItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'item_type_id' => $this->item_type_id ? (int) $this->item_type_id : null,
            'detail' => $this->detail,
            'rarity' => $this->rarity,
            'requires_attunement' => (bool) $this->requires_attunement,
            'is_magic' => (bool) $this->is_magic,
            'cost_cp' => $this->cost_cp ? (int) $this->cost_cp : null,
            'weight' => $this->weight,
            'damage_dice' => $this->damage_dice,
            'versatile_damage' => $this->versatile_damage,
            'damage_type_id' => $this->damage_type_id ? (int) $this->damage_type_id : null,
            'range_normal' => $this->range_normal ? (int) $this->range_normal : null,
            'range_long' => $this->range_long ? (int) $this->range_long : null,
            'armor_class' => $this->armor_class ? (int) $this->armor_class : null,
            'strength_requirement' => $this->strength_requirement ? (int) $this->strength_requirement : null,
            'stealth_disadvantage' => (bool) $this->stealth_disadvantage,
            'description' => $this->description,

            // Charge mechanics (magic items)
            'charges_max' => $this->charges_max ? (int) $this->charges_max : null,
            'recharge_formula' => $this->recharge_formula,
            'recharge_timing' => $this->recharge_timing,

            // Equipment slot for paperdoll UI
            'equipment_slot' => $this->equipment_slot,

            // Computed fields
            'proficiency_category' => $this->proficiency_category,
            'magic_bonus' => $this->magic_bonus,

            // Relationships
            'item_type' => ItemTypeResource::make($this->whenLoaded('itemType')),
            'damage_type' => DamageTypeResource::make($this->whenLoaded('damageType')),
            'properties' => ItemPropertyResource::collection($this->whenLoaded('properties')),
            'abilities' => ItemAbilityResource::collection($this->whenLoaded('abilities')),
            'data_tables' => EntityDataTableResource::collection($this->whenLoaded('dataTables')),
            'sources' => EntitySourceResource::collection($this->whenLoaded('sources')),
            'proficiencies' => ProficiencyResource::collection($this->whenLoaded('proficiencies')),
            'modifiers' => ModifierResource::collection($this->whenLoaded('modifiers')),
            'prerequisites' => EntityPrerequisiteResource::collection($this->whenLoaded('prerequisites')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'spells' => ItemSpellResource::collection($this->whenLoaded('spells')),
            'saving_throws' => SavingThrowResource::collection($this->whenLoaded('savingThrows')),
            'contents' => PackContentResource::collection($this->whenLoaded('contents')),
        ];
    }
}
