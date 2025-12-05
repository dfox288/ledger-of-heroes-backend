<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Cache\LookupCacheService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Base controller for read-only lookup endpoints.
 *
 * Provides common index() and show() implementations with:
 * - Search support via 'q' parameter
 * - Pagination via 'per_page' and 'page' parameters
 * - Optional caching for unfiltered queries
 * - Eager loading of relationships
 *
 * Child controllers must define:
 * - getModelClass(): string - Fully qualified model class name
 * - getResourceClass(): string - Fully qualified resource class name
 * - getIndexRequestClass(): string - Fully qualified index request class name
 * - getRelationships(): array - Array of relationships to eager load (optional)
 * - getCacheMethod(): ?string - Cache method name on LookupCacheService (optional)
 * - getSearchFields(): array - Fields to search in (default: ['name'])
 */
abstract class ReadOnlyLookupController extends Controller
{
    /**
     * Get the model class name.
     */
    abstract protected function getModelClass(): string;

    /**
     * Get the resource class name.
     */
    abstract protected function getResourceClass(): string;

    /**
     * Get the index request class name.
     */
    abstract protected function getIndexRequestClass(): string;

    /**
     * Get relationships to eager load.
     */
    protected function getRelationships(): array
    {
        return [];
    }

    /**
     * Get the cache method name on LookupCacheService.
     * Return null to disable caching.
     */
    protected function getCacheMethod(): ?string
    {
        return null;
    }

    /**
     * Get fields to search in.
     */
    protected function getSearchFields(): array
    {
        return ['name'];
    }

    /**
     * List all records with search and pagination support.
     */
    public function index(FormRequest $request, LookupCacheService $cache): AnonymousResourceCollection
    {
        $modelClass = $this->getModelClass();
        $resourceClass = $this->getResourceClass();
        $cacheMethod = $this->getCacheMethod();

        $query = $modelClass::query();

        // Apply relationships
        $relationships = $this->getRelationships();
        if (! empty($relationships)) {
            $query->with($relationships);
        }

        // Apply search
        if ($request->has('q')) {
            $search = $request->validated('q');
            $searchFields = $this->getSearchFields();

            $query->where(function ($q) use ($search, $searchFields) {
                foreach ($searchFields as $field) {
                    $q->orWhere($field, 'LIKE', "%{$search}%");
                }
            });
        }

        // Pagination
        $perPage = $request->validated('per_page', 50);

        // Use cache for unfiltered queries if cache method is defined
        if ($cacheMethod && ! $request->has('q')) {
            $allRecords = $cache->$cacheMethod();
            $currentPage = $request->input('page', 1);
            $paginated = new LengthAwarePaginator(
                $allRecords->forPage($currentPage, $perPage),
                $allRecords->count(),
                $perPage,
                $currentPage,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            return $resourceClass::collection($paginated);
        }

        return $resourceClass::collection(
            $query->paginate($perPage)
        );
    }

    /**
     * Get a single record.
     */
    public function show(Model $record): JsonResource
    {
        $resourceClass = $this->getResourceClass();

        return new $resourceClass($record);
    }
}
