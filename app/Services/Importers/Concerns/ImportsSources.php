<?php

namespace App\Services\Importers\Concerns;

use App\Models\EntitySource;
use App\Models\Source;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait for importing entity sources (multi-source citations).
 *
 * Handles the common pattern of:
 * 1. Clear existing sources
 * 2. Look up source by code
 * 3. Create EntitySource junction records
 */
trait ImportsSources
{
    /**
     * Import sources for an entity.
     *
     * Clears existing sources and creates new EntitySource records.
     *
     * @param  Model  $entity  The entity (Spell, Race, Item, etc.)
     * @param  array  $sources  Array of ['code' => 'PHB', 'pages' => '123']
     */
    protected function importEntitySources(Model $entity, array $sources): void
    {
        // Clear existing sources
        $entity->sources()->delete();

        // Create new source associations
        foreach ($sources as $sourceData) {
            $source = Source::where('code', $sourceData['code'])->first();

            if ($source) {
                EntitySource::create([
                    'reference_type' => get_class($entity),
                    'reference_id' => $entity->id,
                    'source_id' => $source->id,
                    'pages' => $sourceData['pages'] ?? null,
                ]);
            }
        }
    }
}
