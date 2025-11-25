<?php

namespace App\DTOs;

use App\Http\Requests\FeatIndexRequest;

/**
 * Data Transfer Object for Feat search parameters
 *
 * Properties:
 * - searchQuery: Full-text search query (?q=)
 * - meilisearchFilter: Meilisearch filter syntax (?filter=)
 * - page: Pagination page number
 * - perPage: Results per page
 * - sortBy: Sort field
 * - sortDirection: Sort order (asc/desc)
 */
final readonly class FeatSearchDTO
{
    public function __construct(
        public ?string $searchQuery,
        public ?string $meilisearchFilter,
        public int $page,
        public int $perPage,
        public string $sortBy,
        public string $sortDirection,
    ) {}

    public static function fromRequest(FeatIndexRequest $request): self
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
