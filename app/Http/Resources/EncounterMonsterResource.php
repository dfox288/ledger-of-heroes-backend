<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EncounterMonsterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'monster_id' => $this->monster_id,
            'label' => $this->label,
            'current_hp' => $this->current_hp,
            'max_hp' => $this->max_hp,
            'legendary_actions_used' => $this->legendary_actions_used,
            'legendary_resistance_used' => $this->legendary_resistance_used,
            'monster' => $this->whenLoaded('monster', fn () => [
                'name' => $this->monster->name,
                'slug' => $this->monster->slug,
                'armor_class' => $this->monster->armor_class,
                'armor_type' => $this->monster->armor_type,
                'hit_points_average' => $this->monster->hit_points_average,
                'hit_dice' => $this->monster->hit_dice,
                'speed_walk' => $this->monster->speed_walk,
                'speed_fly' => $this->monster->speed_fly,
                'speed_swim' => $this->monster->speed_swim,
                'speed_burrow' => $this->monster->speed_burrow,
                'speed_climb' => $this->monster->speed_climb,
                'challenge_rating' => $this->monster->challenge_rating,
                // Actions are pre-filtered (reactions excluded) at query level
                'actions' => MonsterActionResource::collection(
                    $this->monster->relationLoaded('actions') ? $this->monster->actions : []
                ),
                // Legendary data for boss fights
                'legendary_actions' => $this->formatLegendaryActions(),
                'legendary_resistance' => $this->formatLegendaryResistance(),
                'lair_actions' => $this->formatLairActions(),
            ]),
        ];
    }

    /**
     * Format legendary actions for the DM screen.
     *
     * Returns structured object with uses_per_round and action list,
     * or null for non-legendary monsters.
     */
    private function formatLegendaryActions(): ?array
    {
        if ($this->monster->legendary_actions_per_round === null) {
            return null;
        }

        $legendaryActions = $this->monster->relationLoaded('legendaryActions')
            ? $this->monster->legendaryActions->where('is_lair_action', false)->values()
            : collect();

        return [
            'uses_per_round' => $this->monster->legendary_actions_per_round,
            'actions' => $legendaryActions->map(fn ($action) => [
                'name' => $action->name,
                'description' => $action->description,
                'action_cost' => $action->action_cost,
            ])->all(),
        ];
    }

    /**
     * Format legendary resistance for the DM screen.
     *
     * Returns structured object with uses_per_day, or null if no resistance.
     */
    private function formatLegendaryResistance(): ?array
    {
        if ($this->monster->legendary_resistance_uses === null) {
            return null;
        }

        return [
            'uses_per_day' => $this->monster->legendary_resistance_uses,
        ];
    }

    /**
     * Format lair actions for the DM screen.
     *
     * Returns array of lair actions, or null if no lair actions.
     */
    private function formatLairActions(): ?array
    {
        if (! $this->monster->relationLoaded('legendaryActions')) {
            return null;
        }

        $lairActions = $this->monster->legendaryActions->where('is_lair_action', true)->values();

        if ($lairActions->isEmpty()) {
            return null;
        }

        return $lairActions->map(fn ($action) => [
            'name' => $action->name,
            'description' => $action->description,
        ])->all();
    }
}
