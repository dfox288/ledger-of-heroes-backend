<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CharacterSpellResource extends JsonResource
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
            'spell' => $this->spell ? [
                'id' => $this->spell->id,
                'name' => $this->spell->name,
                'slug' => $this->spell->slug,
                'level' => $this->spell->level,
                'school' => $this->spell->spellSchool?->name,
                'casting_time' => $this->spell->casting_time,
                'range' => $this->spell->range,
                'components' => $this->spell->components,
                'duration' => $this->spell->duration,
                'concentration' => $this->spell->needs_concentration,
                'ritual' => $this->spell->is_ritual,
            ] : null,
            'spell_slug' => $this->spell_slug,
            'is_dangling' => $this->spell === null,
            'preparation_status' => $this->preparation_status,
            'source' => $this->source,
            'level_acquired' => $this->level_acquired,
            'is_prepared' => $this->spell ? $this->isPrepared() : false,
            'is_always_prepared' => $this->spell ? $this->isAlwaysPrepared() : false,
        ];
    }
}
