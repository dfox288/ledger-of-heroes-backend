<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for class section counts.
 *
 * Provides counts for lazy-loading accordions in the UI.
 * Features count excludes multiclass-only features (e.g., "Multiclass Cleric").
 *
 * @property int|null $features Feature count (excluding multiclass-only)
 * @property int|null $multiclass_features Multiclass-only feature count
 * @property int|null $proficiencies Proficiency count
 * @property int|null $traits Trait count
 * @property int|null $subclasses Subclass count
 * @property int|null $spells Spell count
 * @property int|null $counters Counter count
 * @property int|null $optional_features Optional feature count
 */
class SectionCountsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{
     *   features: int|null,
     *   multiclass_features: int|null,
     *   proficiencies: int|null,
     *   traits: int|null,
     *   subclasses: int|null,
     *   spells: int|null,
     *   counters: int|null,
     *   optional_features: int|null
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'features' => $this->resource['features'],
            'multiclass_features' => $this->resource['multiclass_features'],
            'proficiencies' => $this->resource['proficiencies'],
            'traits' => $this->resource['traits'],
            'subclasses' => $this->resource['subclasses'],
            'spells' => $this->resource['spells'],
            'counters' => $this->resource['counters'],
            'optional_features' => $this->resource['optional_features'],
        ];
    }
}
