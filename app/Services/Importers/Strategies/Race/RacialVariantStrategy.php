<?php

namespace App\Services\Importers\Strategies\Race;

use App\Models\Race;
use Illuminate\Support\Str;

class RacialVariantStrategy extends AbstractRaceStrategy
{
    /**
     * Variants have a variant_of field.
     */
    public function appliesTo(array $data): bool
    {
        return ! empty($data['variant_of']);
    }

    /**
     * Enhance variant data with type extraction and parent resolution.
     */
    public function enhance(array $data): array
    {
        $variantOfName = $data['variant_of'];

        // Parse variant type from name FIRST: "Dragonborn (Gold)" → "Gold"
        if (preg_match('/\(([^)]+)\)/', $data['name'], $matches)) {
            $data['variant_type'] = $matches[1];
            $variantTypeSlug = Str::slug($matches[1]);
        } else {
            $variantTypeSlug = null;
        }

        // Resolve parent race
        $parentRace = Race::where('name', $variantOfName)->first();

        if ($parentRace) {
            $data['parent_race_id'] = $parentRace->id;

            // Generate slug using parent's ACTUAL slug
            if ($variantTypeSlug) {
                $data['slug'] = "{$parentRace->slug}-{$variantTypeSlug}";
            } else {
                // No variant type in parentheses, use full name
                $data['slug'] = Str::slug($data['name']);
            }
        } else {
            $this->addWarning("Parent race '{$variantOfName}' not found for variant '{$data['name']}'");
            $data['parent_race_id'] = null;
            $data['slug'] = Str::slug($data['name']);
        }

        // Track metric
        $this->incrementMetric('variants_processed');

        return $data;
    }
}
