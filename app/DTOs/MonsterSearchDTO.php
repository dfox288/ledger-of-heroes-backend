<?php

namespace App\DTOs;

use App\Http\Requests\MonsterIndexRequest;

/**
 * Data Transfer Object for monster search operations.
 *
 * Encapsulates search parameters for Meilisearch-based filtering and pagination.
 * Legacy MySQL filter parameters have been removed in favor of the unified
 * Meilisearch filter syntax via the `meilisearchFilter` parameter.
 */
final readonly class MonsterSearchDTO
{
    public function __construct(
        public ?string $searchQuery,
        public int $perPage,
        public int $page,
        public string $sortBy,
        public string $sortDirection,
        public ?string $meilisearchFilter = null,
    ) {}

    public static function fromRequest(MonsterIndexRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            searchQuery: $validated['q'] ?? null,
            perPage: $validated['per_page'] ?? 15,
            page: $validated['page'] ?? 1,
            sortBy: $validated['sort_by'] ?? 'name',
            sortDirection: $validated['sort_direction'] ?? 'asc',
            meilisearchFilter: $validated['filter'] ?? null,
        );
    }
}
