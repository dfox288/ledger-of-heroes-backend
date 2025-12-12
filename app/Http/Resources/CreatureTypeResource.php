<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreatureTypeResource extends JsonResource
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
            'slug' => $this->slug,
            'name' => $this->name,
            'typically_immune_to_poison' => $this->typically_immune_to_poison,
            'typically_immune_to_charmed' => $this->typically_immune_to_charmed,
            'typically_immune_to_frightened' => $this->typically_immune_to_frightened,
            'typically_immune_to_exhaustion' => $this->typically_immune_to_exhaustion,
            'requires_sustenance' => $this->requires_sustenance,
            'requires_sleep' => $this->requires_sleep,
            'description' => $this->description,
        ];
    }
}
