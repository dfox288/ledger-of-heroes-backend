<?php

namespace App\DTOs;

use App\Http\Requests\BackgroundIndexRequest;

/**
 * Data Transfer Object for Background search parameters
 *
 * Decouples the service layer from HTTP concerns by providing
 * a framework-agnostic way to pass search parameters.
 */
final readonly class BackgroundSearchDTO
{
    /**
     * @param  string|null  $searchQuery  Full-text search query (Meilisearch)
     * @param  string|null  $meilisearchFilter  Meilisearch filter expression
     * @param  int  $page  Page number for pagination (1-based)
     * @param  int  $perPage  Number of results per page (1-100)
     * @param  string  $sortBy  Column to sort by
     * @param  string  $sortDirection  Sort direction (asc/desc)
     */
    public function __construct(
        public ?string $searchQuery,
        public ?string $meilisearchFilter,
        public int $page,
        public int $perPage,
        public string $sortBy,
        public string $sortDirection,
    ) {}

    /**
     * Create DTO from validated Form Request data
     */
    public static function fromRequest(BackgroundIndexRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            searchQuery: $validated['q'] ?? null,
            meilisearchFilter: $validated['filter'] ?? null,
            page: $validated['page'] ?? 1,
            perPage: $validated['per_page'] ?? 15,
            sortBy: $validated['sort_by'] ?? 'name',
            sortDirection: $validated['sort_direction'] ?? 'asc',
        );
    }
}
