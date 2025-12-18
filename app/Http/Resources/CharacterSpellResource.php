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
                'description' => $this->spell->description,
                'higher_levels' => $this->spell->higher_levels,
                // Combat fields (Issue #756)
                'damage_types' => $this->spell->effects
                    ->filter(fn ($e) => $e->damageType)
                    ->pluck('damageType.name')
                    ->unique()
                    ->values()
                    ->all(),
                'saving_throw' => $this->spell->savingThrows->first()?->code,
                'attack_type' => $this->spell->attack_type,
            ] : null,
            'spell_slug' => $this->spell_slug,
            'is_dangling' => $this->spell === null,
            'preparation_status' => $this->preparation_status,
            'source' => $this->source,
            'class_slug' => $this->class_slug,
            'level_acquired' => $this->level_acquired,
            'is_prepared' => $this->spell ? $this->isPrepared() : false,
            'is_always_prepared' => $this->spell ? $this->isAlwaysPrepared() : false,
        ];
    }
}
