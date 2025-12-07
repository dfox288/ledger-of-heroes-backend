<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConditionResource extends JsonResource
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
            'name' => $this->name,
            'slug' => $this->slug,
            'full_slug' => $this->full_slug,
            'description' => $this->description,
            /** @var int|null Number of monsters that inflict this condition */
            'monster_count' => $this->monsters_count ?? null,
            /** @var int|null Number of spells that inflict this condition */
            'spell_count' => $this->spells_count ?? null,
        ];
    }
}
