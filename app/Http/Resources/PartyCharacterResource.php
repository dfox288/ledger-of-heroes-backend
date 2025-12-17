<?php

namespace App\Http\Resources;

use App\Models\Character;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight resource for characters in party listings.
 *
 * @mixin Character
 */
class PartyCharacterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'name' => $this->name,
            'level' => (int) $this->total_level,
            'class_name' => $this->getPrimaryClassName(),
            'portrait' => $this->getPortraitData(),
        ];
    }

    /**
     * Get the primary class name for display.
     */
    private function getPrimaryClassName(): ?string
    {
        $primaryClass = $this->characterClasses
            ->sortByDesc('pivot.level')
            ->first();

        return $primaryClass?->characterClass?->name;
    }

    /**
     * Get portrait thumbnail data.
     */
    private function getPortraitData(): ?array
    {
        $portrait = $this->getFirstMedia('portrait');

        if (! $portrait) {
            return null;
        }

        return [
            'thumb' => $portrait->getUrl('thumb'),
        ];
    }
}
