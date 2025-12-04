<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CharacterConditionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isExhaustion = $this->condition->slug === 'exhaustion';

        return [
            'id' => $this->id,
            'condition' => [
                'id' => $this->condition->id,
                'name' => $this->condition->name,
                'slug' => $this->condition->slug,
            ],
            'level' => $this->level,
            'source' => $this->source,
            'duration' => $this->duration,
            'is_exhaustion' => $isExhaustion,
            'exhaustion_warning' => $isExhaustion && $this->level === 6
                ? 'Level 6 exhaustion results in death'
                : null,
        ];
    }
}
