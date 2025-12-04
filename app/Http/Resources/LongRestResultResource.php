<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for long rest action results.
 *
 * Transforms long rest service response into API format with
 * HP restoration, hit dice recovery, spell slot reset, death saves cleared,
 * and features that were reset.
 *
 * @property array{hp_restored: int, hit_dice_recovered: int, spell_slots_reset: bool, death_saves_cleared: bool, features_reset: array<string>} $resource
 */
class LongRestResultResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'hp_restored' => $this->resource['hp_restored'] ?? 0,
            'hit_dice_recovered' => $this->resource['hit_dice_recovered'] ?? 0,
            'spell_slots_reset' => $this->resource['spell_slots_reset'] ?? false,
            'death_saves_cleared' => $this->resource['death_saves_cleared'] ?? false,
            'features_reset' => $this->resource['features_reset'] ?? [],
        ];
    }
}
