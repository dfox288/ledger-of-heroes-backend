<?php

namespace App\Services\Importers\Strategies\CharacterClass;

use App\Models\AbilityScore;
use App\Services\Importers\Strategies\AbstractImportStrategy;

class BaseClassStrategy extends AbstractImportStrategy
{
    /**
     * Base classes have hit_die > 0.
     */
    public function appliesTo(array $data): bool
    {
        return ($data['hit_die'] ?? 0) > 0;
    }

    /**
     * Enhance base class data with spellcasting detection and validation.
     */
    public function enhance(array $data): array
    {
        // Validate required fields
        if (empty($data['hit_die'])) {
            $this->addWarning("Base class '{$data['name']}' missing hit_die");
        }

        // Set parent_class_id to null (this is a base class)
        $data['parent_class_id'] = null;

        // Resolve spellcasting ability if present
        if (! empty($data['spellcasting_ability'])) {
            $ability = AbilityScore::where('name', $data['spellcasting_ability'])->first();
            $data['spellcasting_ability_id'] = $ability?->id;

            $this->incrementMetric('spellcasters_detected');
        } else {
            $data['spellcasting_ability_id'] = null;
            $this->incrementMetric('martial_classes');
        }

        // Track metric
        $this->incrementMetric('base_classes_processed');

        return $data;
    }
}
