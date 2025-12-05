<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Services\Cache\EntityCacheService;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait CachesEntityShow
 *
 * Provides a reusable cache-with-fallback pattern for entity show() methods.
 * Eliminates code duplication across 6 main entity controllers.
 *
 * Usage:
 * ```php
 * use CachesEntityShow;
 *
 * public function show(SpellShowRequest $request, Spell $spell, EntityCacheService $cache, SpellSearchService $service)
 * {
 *     return $this->showWithCache(
 *         request: $request,
 *         entity: $spell,
 *         cache: $cache,
 *         cacheMethod: 'getSpell',
 *         resourceClass: SpellResource::class,
 *         defaultRelationships: $service->getShowRelationships()
 *     );
 * }
 * ```
 */
trait CachesEntityShow
{
    /**
     * Show a single entity with cache-fallback pattern.
     *
     * Implements the standard cache-with-database-fallback pattern:
     * 1. Try cache first using EntityCacheService
     * 2. Fall back to route model binding result if cache misses
     * 3. Load relationships (from include param or defaults)
     * 4. Return API resource
     *
     * @param  \Illuminate\Foundation\Http\FormRequest  $request  Validated request
     * @param  Model  $entity  Entity from route model binding
     * @param  EntityCacheService  $cache  Cache service
     * @param  string  $cacheMethod  Cache method name (getSpell, getClass, etc.)
     * @param  string  $resourceClass  API resource class name
     * @param  array  $defaultRelationships  Default relationships to load
     * @param  callable|null  $beforeLoad  Optional callback to modify entity before relationship loading
     * @return mixed API resource instance
     */
    protected function showWithCache(
        $request,
        Model $entity,
        EntityCacheService $cache,
        string $cacheMethod,
        string $resourceClass,
        array $defaultRelationships = [],
        ?callable $beforeLoad = null
    ) {
        $validated = $request->validated();

        // Try cache first
        $cachedEntity = $cache->$cacheMethod($entity->id);

        if ($cachedEntity) {
            // If include parameter provided, use it; otherwise load defaults
            $includes = $validated['include'] ?? $defaultRelationships;

            // Optional pre-load callback for custom logic (e.g., ClassController's parentClass handling)
            if ($beforeLoad) {
                $includes = $beforeLoad($cachedEntity, $includes);
            }

            $cachedEntity->load($includes);

            return new $resourceClass($cachedEntity);
        }

        // Fallback to route model binding result (should rarely happen)
        $includes = $validated['include'] ?? $defaultRelationships;

        // Optional pre-load callback for custom logic
        if ($beforeLoad) {
            $includes = $beforeLoad($entity, $includes);
        }

        $entity->load($includes);

        return new $resourceClass($entity);
    }
}
