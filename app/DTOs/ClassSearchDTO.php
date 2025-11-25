<?php

namespace App\DTOs;

use App\Http\Requests\ClassIndexRequest;

/**
 * DTO for class search operations using Meilisearch.
 *
 * Properties:
 * - searchQuery: Full-text search term (from 'q' parameter)
 * - meilisearchFilter: Meilisearch filter syntax (from 'filter' parameter)
 * - page: Pagination page number
 * - perPage: Results per page
 * - sortBy: Sort field name
 * - sortDirection: 'asc' or 'desc'
 */
final readonly class ClassSearchDTO
{
    public function __construct(
        public ?string $searchQuery,
        public ?string $meilisearchFilter,
        public int $page,
        public int $perPage,
        public string $sortBy,
        public string $sortDirection,
    ) {}

    public static function fromRequest(ClassIndexRequest $request): self
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
