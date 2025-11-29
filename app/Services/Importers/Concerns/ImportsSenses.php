<?php

namespace App\Services\Importers\Concerns;

use App\Models\EntitySense;
use App\Models\Sense;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait for importing senses (darkvision, blindsight, etc.).
 *
 * Handles the common pattern of:
 * 1. Clear existing senses for entity
 * 2. Look up sense type by slug
 * 3. Create EntitySense records with polymorphic reference
 */
trait ImportsSenses
{
    /**
     * Cache for sense lookups.
     */
    protected static ?array $senseCache = null;

    /**
     * Import senses for an entity.
     *
     * @param  Model  $entity  The entity (Monster, Race)
     * @param  array  $sensesData  Array of parsed sense data from parser
     *                             Each item: ['type' => 'darkvision', 'range' => 60, 'is_limited' => false, 'notes' => null]
     */
    protected function importEntitySenses(Model $entity, array $sensesData): void
    {
        // Clear existing senses for this entity
        $entity->senses()->delete();

        if (empty($sensesData)) {
            return;
        }

        // Initialize cache if needed
        if (self::$senseCache === null) {
            self::$senseCache = Sense::pluck('id', 'slug')->all();
        }

        foreach ($sensesData as $senseData) {
            $senseType = $senseData['type'];
            $senseId = self::$senseCache[$senseType] ?? null;

            if ($senseId === null) {
                // Skip unknown sense types
                continue;
            }

            EntitySense::create([
                'reference_type' => get_class($entity),
                'reference_id' => $entity->id,
                'sense_id' => $senseId,
                'range_feet' => $senseData['range'],
                'is_limited' => $senseData['is_limited'] ?? false,
                'notes' => $senseData['notes'] ?? null,
            ]);
        }
    }

    /**
     * Reset the sense cache (useful between test runs).
     */
    protected static function resetSenseCache(): void
    {
        self::$senseCache = null;
    }
}
