<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for a single ability score bonus.
 *
 * Represents a single bonus to an ability score from a source (race, feat, etc.)
 * with metadata about whether it's from a choice and if that choice is resolved.
 *
 * @property array{
 *     source_type: string,
 *     source_name: string,
 *     source_slug: string,
 *     ability_code: string,
 *     ability_name: string,
 *     value: int,
 *     is_choice: bool,
 *     choice_resolved?: bool,
 *     modifier_id?: int
 * } $resource
 */
class AbilityBonusResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{
     *     source_type: string,
     *     source_name: string,
     *     source_slug: string,
     *     ability_code: string,
     *     ability_name: string,
     *     value: int,
     *     is_choice: bool,
     *     choice_resolved?: bool,
     *     modifier_id?: int
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var string Source type (race, feat, class_feature, item) */
            'source_type' => $this->resource['source_type'],
            /** @var string Display name of the source entity */
            'source_name' => $this->resource['source_name'],
            /** @var string Full slug for linking/identification */
            'source_slug' => $this->resource['source_slug'],
            /** @var string Ability code (STR, DEX, CON, INT, WIS, CHA) */
            'ability_code' => $this->resource['ability_code'],
            /** @var string Full ability name (Strength, Dexterity, etc.) */
            'ability_name' => $this->resource['ability_name'],
            /** @var int Bonus amount (typically 1 or 2) */
            'value' => $this->resource['value'],
            /** @var bool Whether this bonus came from a choice modifier */
            'is_choice' => $this->resource['is_choice'],
            /** @var bool Only present when is_choice=true - whether the choice slot has been picked */
            'choice_resolved' => $this->when(
                $this->resource['is_choice'],
                fn () => $this->resource['choice_resolved'] ?? false
            ),
            /** @var int Only present when is_choice=true - links to choice template for undo/redo */
            'modifier_id' => $this->when(
                $this->resource['is_choice'],
                fn () => $this->resource['modifier_id'] ?? null
            ),
        ];
    }
}
