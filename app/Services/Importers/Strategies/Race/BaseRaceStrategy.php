<?php

namespace App\Services\Importers\Strategies\Race;

class BaseRaceStrategy extends AbstractRaceStrategy
{
    /**
     * Base races have no parent and are not variants.
     */
    public function appliesTo(array $data): bool
    {
        return empty($data['base_race_name']) && empty($data['variant_of']);
    }

    /**
     * Enhance base race data with validation and metadata.
     */
    public function enhance(array $data): array
    {
        // Validate required fields
        if (empty($data['size_code'])) {
            $this->addWarning("Base race '{$data['name']}' missing size_code");
        }

        if (empty($data['speed'])) {
            $this->addWarning("Base race '{$data['name']}' missing speed");
        }

        // Set parent_race_id to null (this is a base race)
        $data['parent_race_id'] = null;

        // Track metric
        $this->incrementMetric('base_races_processed');

        return $data;
    }
}
