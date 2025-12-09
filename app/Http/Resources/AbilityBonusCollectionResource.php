<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for a collection of ability bonuses with summary totals.
 *
 * Wraps multiple ability bonuses with calculated totals per ability score.
 * The totals only include resolved bonuses (fixed bonuses and resolved choices).
 *
 * @property array{bonuses: \Illuminate\Support\Collection, totals: array<string, int>} $resource
 */
class AbilityBonusCollectionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{
     *     bonuses: array<array{
     *         source_type: string,
     *         source_name: string,
     *         source_slug: string,
     *         ability_code: string,
     *         ability_name: string,
     *         value: int,
     *         is_choice: bool,
     *         choice_resolved?: bool,
     *         modifier_id?: int
     *     }>,
     *     totals: array<string, int>
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var array<array{source_type: string, source_name: string, source_slug: string, ability_code: string, ability_name: string, value: int, is_choice: bool, choice_resolved?: bool, modifier_id?: int}> All ability bonuses from all sources */
            'bonuses' => AbilityBonusResource::collection($this->resource['bonuses'])->resolve(),
            /** @var array<string, int> Sum of all resolved bonuses per ability (STR, DEX, CON, INT, WIS, CHA) */
            'totals' => $this->resource['totals'],
        ];
    }
}
