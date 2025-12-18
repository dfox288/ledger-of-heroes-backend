<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsRelatedModels;
use App\Models\Character;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight resource for character list views.
 *
 * Returns only essential fields needed to display a character in a list:
 * - Identity (id, public_id, name)
 * - Level
 * - Race and class names (not full objects)
 * - Portrait thumbnail
 * - Completion status
 *
 * This avoids the expensive computations in CharacterResource (ability modifiers,
 * spell slots, counters, proficiency checks) that are unnecessary for list views.
 *
 * @see https://github.com/dfox288/ledger-of-heroes/issues/721
 *
 * @mixin Character
 */
class CharacterListResource extends JsonResource
{
    use FormatsRelatedModels;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'name' => $this->name,
            'level' => (int) $this->total_level,
            'race' => $this->formatEntity($this->race),
            'class_name' => $this->primary_class?->name,
            'classes' => $this->getClassesLightweight(),
            'portrait' => $this->getPortraitThumb(),
            'is_complete' => (bool) $this->is_complete,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Get lightweight classes array for list display.
     *
     * @return array<int, array{name: string, level: int, is_primary: bool}>
     */
    private function getClassesLightweight(): array
    {
        if (! $this->relationLoaded('characterClasses')) {
            return [];
        }

        return $this->characterClasses
            ->map(fn ($pivot) => [
                'name' => $pivot->characterClass?->name,
                'level' => (int) $pivot->level,
                'is_primary' => (bool) $pivot->is_primary,
            ])
            ->filter(fn ($class) => $class['name'] !== null)
            ->values()
            ->all();
    }

    /**
     * Get portrait thumbnail URL only.
     */
    private function getPortraitThumb(): ?string
    {
        // Check for uploaded media first
        if ($this->relationLoaded('media')) {
            $media = $this->getFirstMedia('portrait');
            if ($media) {
                return $media->hasGeneratedConversion('thumb')
                    ? $media->getUrl('thumb')
                    : $media->getUrl();
            }
        }

        // Fall back to external URL
        return $this->portrait_url;
    }
}
