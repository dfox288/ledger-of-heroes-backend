<?php

namespace App\Http\Resources;

use App\DTOs\CharacterStatsDTO;
use App\Models\Character;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Combat-focused aggregate resource for battle interfaces.
 *
 * Returns all combat-relevant data in a single response, reducing
 * frontend round-trips for combat scenarios.
 *
 * @property Character $resource
 */
class CharacterCombatResource extends JsonResource
{
    public function __construct(
        Character $character,
        private CharacterStatsDTO $stats
    ) {
        parent::__construct($character);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // Character identity
            'character' => [
                'id' => $this->resource->id,
                'public_id' => $this->resource->public_id,
                'name' => $this->resource->name,
                'level' => $this->resource->total_level,
            ],

            // Core combat stats (from DTO)
            'combat_stats' => [
                'armor_class' => $this->stats->armorClass,
                'hit_points' => [
                    'current' => $this->stats->currentHitPoints,
                    'max' => $this->stats->maxHitPoints,
                    'temp' => $this->stats->tempHitPoints,
                ],
                'initiative_bonus' => $this->stats->initiativeBonus,
                'proficiency_bonus' => $this->stats->proficiencyBonus,
                'speed' => $this->stats->speed,
            ],

            // Saving throws (from DTO)
            'saving_throws' => $this->stats->savingThrows,

            // Weapons with attack/damage (from DTO)
            'weapons' => $this->stats->weapons,
            'unarmed_strike' => $this->stats->unarmedStrike,

            // Spell slots (from DTO)
            'spell_slots' => [
                'standard' => (object) ($this->stats->spellSlots['slots'] ?? []),
                'pact_magic' => $this->stats->spellSlots['pact_magic'] ?? null,
            ],

            // Prepared spells (combat-relevant subset)
            'prepared_spells' => $this->getPreparedSpells(),

            // Class resources (Action Surge, Rage, etc.)
            'resources' => $this->getResources(),

            // Active conditions
            'conditions' => CharacterConditionResource::collection(
                $this->resource->conditions
            ),

            // Death saves
            'death_saves' => [
                'successes' => $this->resource->death_save_successes,
                'failures' => $this->resource->death_save_failures,
                'is_conscious' => $this->resource->current_hit_points > 0,
                'is_dead' => (bool) $this->resource->is_dead,
            ],

            // Defenses (from DTO)
            'defenses' => [
                'resistances' => $this->stats->damageResistances,
                'immunities' => $this->stats->damageImmunities,
                'vulnerabilities' => $this->stats->damageVulnerabilities,
                'condition_immunities' => $this->stats->conditionImmunities,
            ],

            // Spellcasting per-class (for multiclass)
            'spellcasting' => $this->stats->spellcasting,
        ];
    }

    /**
     * Get prepared spells with combat-relevant details.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getPreparedSpells(): array
    {
        return $this->resource->spells
            ->filter(fn ($cs) => $cs->isPrepared())
            ->map(fn ($cs) => [
                'id' => $cs->id,
                'name' => $cs->spell?->name,
                'level' => $cs->spell?->level,
                'school' => $cs->spell?->spellSchool?->name,
                'concentration' => (bool) $cs->spell?->needs_concentration,
                'ritual' => (bool) $cs->spell?->is_ritual,
                'casting_time' => $cs->spell?->casting_time,
                'range' => $cs->spell?->range,
                'damage_types' => $cs->spell?->effects
                    ?->pluck('damageType.name')
                    ->filter()
                    ->unique()
                    ->values()
                    ->toArray() ?? [],
            ])
            ->values()
            ->toArray();
    }

    /**
     * Get class resources with recharge timing.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getResources(): array
    {
        return $this->resource->counters->map(fn ($counter) => [
            'name' => $counter->counter_name,
            'uses' => $counter->current_uses ?? $counter->max_uses,
            'max' => $counter->max_uses,
            'recharge' => match ($counter->reset_timing) {
                'S' => 'short_rest',
                'L' => 'long_rest',
                'D' => 'dawn',
                default => $counter->reset_timing,
            },
        ])->values()->toArray();
    }
}
