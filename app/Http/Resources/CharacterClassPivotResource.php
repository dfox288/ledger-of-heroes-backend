<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CharacterClassPivotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'class' => $this->characterClass ? [
                'id' => $this->characterClass->id,
                'name' => $this->characterClass->name,
                'slug' => $this->characterClass->slug,
            ] : null,
            'class_slug' => $this->class_slug,
            'is_dangling' => $this->characterClass === null,
            'subclass' => $this->subclass ? [
                'id' => $this->subclass->id,
                'name' => $this->subclass->name,
                'slug' => $this->subclass->slug,
            ] : null,
            'subclass_slug' => $this->subclass_slug,
            'level' => $this->level,
            'is_primary' => $this->is_primary,
            'order' => $this->order,
            'hit_dice' => $this->characterClass ? [
                'die' => 'd'.$this->characterClass->hit_die,
                'max' => $this->max_hit_dice,
                'spent' => $this->hit_dice_spent,
                'available' => $this->available_hit_dice,
            ] : null,
        ];
    }
}
