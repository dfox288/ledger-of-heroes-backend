<?php

namespace App\Http\Resources;

use App\Models\CharacterTrait;
use App\Models\ClassFeature;
use App\Models\Feat;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CharacterFeatureResource extends JsonResource
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
            'source' => $this->source,
            'level_acquired' => $this->level_acquired,
            'feature_type' => $this->getSimpleFeatureType(),

            // Limited-use tracking
            'uses_remaining' => $this->uses_remaining,
            'max_uses' => $this->max_uses,
            'has_limited_uses' => $this->hasLimitedUses(),

            // Feature details (polymorphic)
            'feature' => $this->formatFeature(),
        ];
    }

    /**
     * Get a simplified feature type name.
     */
    private function getSimpleFeatureType(): string
    {
        return match ($this->feature_type) {
            ClassFeature::class => 'class_feature',
            CharacterTrait::class => 'trait',
            Feat::class => 'feat',
            default => class_basename($this->feature_type),
        };
    }

    /**
     * Format the feature details based on type.
     */
    private function formatFeature(): ?array
    {
        if (! $this->feature) {
            return null;
        }

        $feature = $this->feature;

        // Base fields all feature types share
        $data = [
            'id' => $feature->id,
        ];

        // Type-specific fields
        if ($feature instanceof ClassFeature) {
            $data['name'] = $feature->feature_name;
            $data['description'] = $feature->description;
            $data['level'] = $feature->level;
            $data['is_optional'] = $feature->is_optional;
        } elseif ($feature instanceof CharacterTrait) {
            $data['name'] = $feature->name;
            $data['description'] = $feature->description;
            $data['category'] = $feature->category;
        } elseif ($feature instanceof Feat) {
            $data['name'] = $feature->name;
            $data['slug'] = $feature->slug;
            $data['description'] = $feature->description;
            $data['prerequisite'] = $feature->prerequisite;
        } else {
            // Generic fallback
            $data['name'] = $feature->name ?? $feature->feature_name ?? null;
            $data['description'] = $feature->description ?? null;
        }

        return $data;
    }
}
