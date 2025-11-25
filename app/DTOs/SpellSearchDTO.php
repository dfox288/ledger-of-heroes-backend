<?php

namespace App\DTOs;

use App\Http\Requests\SpellIndexRequest;

/**
 * Data Transfer Object for Spell search parameters
 *
 * Properties:
 * - searchQuery: Full-text search query
 * - meilisearchFilter: Meilisearch filter syntax
 * - page: Pagination page number
 * - perPage: Results per page
 * - sortBy: Sort field
 * - sortDirection: Sort order (asc/desc)
 */
final readonly class SpellSearchDTO
{
    public function __construct(
        public ?string $searchQuery,
        public ?string $meilisearchFilter,
        public int $page,
        public int $perPage,
        public string $sortBy,
        public string $sortDirection,
    ) {}

    public static function fromRequest(SpellIndexRequest $request): self
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
