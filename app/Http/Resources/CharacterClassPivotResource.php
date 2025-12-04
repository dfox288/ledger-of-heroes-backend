<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CharacterClassPivotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'class' => [
                'id' => $this->characterClass->id,
                'name' => $this->characterClass->name,
                'slug' => $this->characterClass->slug,
            ],
            'subclass' => $this->subclass ? [
                'id' => $this->subclass->id,
                'name' => $this->subclass->name,
                'slug' => $this->subclass->slug,
            ] : null,
            'level' => $this->level,
            'is_primary' => $this->is_primary,
            'order' => $this->order,
            'hit_dice' => [
                'die' => 'd'.$this->characterClass->hit_die,
                'max' => $this->max_hit_dice,
                'spent' => $this->hit_dice_spent,
                'available' => $this->available_hit_dice,
            ],
        ];
    }
}
