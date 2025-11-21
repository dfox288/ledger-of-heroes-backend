<?php

namespace App\Services\Importers\Concerns;

use App\Exceptions\Lookup\EntityNotFoundException;
use Illuminate\Database\Eloquent\Model;

/**
 * Provides generic caching for lookup table queries.
 *
 * This trait eliminates the need for model-specific cache arrays
 * and methods like getItemTypeId(), getDamageTypeId(), etc.
 *
 * Usage:
 *   $source = $this->cachedFind(Source::class, 'code', 'PHB');
 *   $itemTypeId = $this->cachedFindId(ItemType::class, 'code', 'W');
 */
trait CachesLookupTables
{
    /**
     * Generic cache storage: [model][column][value] => Model|null
     */
    private array $lookupCache = [];

    /**
     * Find a model by column value with caching.
     *
     * @param  class-string<Model>  $model  The model class to query
     * @param  string  $column  The column to search by
     * @param  mixed  $value  The value to search for
     * @param  bool  $useFail  Use firstOrFail() instead of first()
     * @return Model|null The found model or null
     *
     * @throws EntityNotFoundException
     */
    protected function cachedFind(string $model, string $column, mixed $value, bool $useFail = true): ?Model
    {
        // Normalize value to uppercase for consistent cache keys AND queries
        $normalizedValue = strtoupper((string) $value);

        // Check cache
        if (! isset($this->lookupCache[$model][$column][$normalizedValue])) {
            try {
                // Query database with normalized value
                $query = $model::where($column, $normalizedValue);
                $result = $useFail ? $query->firstOrFail() : $query->first();

                // Cache result (even if null)
                $this->lookupCache[$model][$column][$normalizedValue] = $result;
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                $entityType = class_basename($model);
                throw new EntityNotFoundException($entityType, $normalizedValue, $column);
            }
        }

        return $this->lookupCache[$model][$column][$normalizedValue];
    }

    /**
     * Find a model's ID by column value with caching.
     *
     * @param  class-string<Model>  $model  The model class to query
     * @param  string  $column  The column to search by
     * @param  mixed  $value  The value to search for
     * @param  bool  $useFail  Use firstOrFail() instead of first()
     * @return int|null The model's ID or null
     *
     * @throws EntityNotFoundException
     */
    protected function cachedFindId(string $model, string $column, mixed $value, bool $useFail = true): ?int
    {
        $result = $this->cachedFind($model, $column, $value, $useFail);

        return $result?->id;
    }
}
