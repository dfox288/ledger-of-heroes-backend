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
            // Scaled effects based on character level (Issue #785)
            'scaled_effects' => $this->getScaledEffects(),
        ];
    }

    /**
     * Get spell effects scaled to the character's level.
     *
     * For cantrips with character_level scaling (e.g., Fire Bolt),
     * returns only the effect tier appropriate for the character's level.
     *
     * Example: A level 5 character with Fire Bolt gets 2d10 (tier 5),
     * not 1d10 (tier 0) or all four tiers.
     *
     * @return array<int, array{effect_type: string, dice_formula: string|null, damage_type: string|null}>
     */
    private function getScaledEffects(): array
    {
        // No spell or no effects
        if (! $this->spell || ! $this->relationLoaded('spell') || ! $this->spell->relationLoaded('effects')) {
            return [];
        }

        // Get character level from loaded relationship
        $characterLevel = $this->character?->total_level ?? 1;

        // Filter to character_level scaling effects only
        $scalingEffects = $this->spell->effects
            ->filter(fn ($e) => $e->scaling_type === 'character_level');

        if ($scalingEffects->isEmpty()) {
            return [];
        }

        // Group by effect type and damage type to find the highest applicable tier for each
        $grouped = $scalingEffects->groupBy(function ($effect) {
            return $effect->effect_type.':'.$effect->damage_type_id;
        });

        $result = [];

        foreach ($grouped as $effects) {
            // Find the highest tier where min_character_level <= character level
            $applicableEffect = $effects
                ->filter(fn ($e) => ($e->min_character_level ?? 0) <= $characterLevel)
                ->sortByDesc('min_character_level')
                ->first();

            if ($applicableEffect) {
                $result[] = [
                    'effect_type' => $applicableEffect->effect_type,
                    'dice_formula' => $applicableEffect->dice_formula,
                    'damage_type' => $applicableEffect->damageType?->name,
                ];
            }
        }

        return $result;
    }
}
