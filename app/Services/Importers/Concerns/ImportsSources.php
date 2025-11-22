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
 * 2. Optionally deduplicate sources by code and merge page numbers
 * 3. Look up source by code
 * 4. Create EntitySource junction records
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
     * @param  bool  $deduplicate  Whether to merge duplicate source codes and combine page numbers
     */
    protected function importEntitySources(Model $entity, array $sources, bool $deduplicate = false): void
    {
        // Clear existing sources
        $entity->sources()->delete();

        // Optionally deduplicate sources by code
        if ($deduplicate) {
            $sources = $this->deduplicateSources($sources);
        }

        // Create new source associations
        foreach ($sources as $sourceData) {
            $source = $this->lookupSource($sourceData['code']);

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

    /**
     * Deduplicate sources by code and merge page numbers.
     *
     * Example: [['code' => 'XGE', 'pages' => '137'], ['code' => 'XGE', 'pages' => '83']]
     *       → [['code' => 'XGE', 'pages' => '137, 83']]
     *
     * @param  array  $sources  Array of source data
     * @return array Deduplicated sources with merged page numbers
     */
    private function deduplicateSources(array $sources): array
    {
        $sourcesByCode = [];

        foreach ($sources as $sourceData) {
            $code = $sourceData['code'];

            if (! isset($sourcesByCode[$code])) {
                $sourcesByCode[$code] = [];
            }

            if (! empty($sourceData['pages'])) {
                $sourcesByCode[$code][] = $sourceData['pages'];
            }
        }

        $deduplicated = [];
        foreach ($sourcesByCode as $code => $pagesList) {
            $deduplicated[] = [
                'code' => $code,
                'pages' => implode(', ', $pagesList),
            ];
        }

        return $deduplicated;
    }

    /**
     * Look up a source by code.
     *
     * Uses CachesLookupTables trait if available, otherwise direct query.
     *
     * @param  string  $code  Source code (e.g., 'PHB', 'XGE')
     */
    private function lookupSource(string $code): ?Source
    {
        // Use cached lookup if trait is available
        if (method_exists($this, 'cachedFind')) {
            return $this->cachedFind(Source::class, 'code', $code);
        }

        // Fallback to direct query
        return Source::where('code', $code)->first();
    }
}
