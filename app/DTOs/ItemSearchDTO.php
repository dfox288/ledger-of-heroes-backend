<?php

namespace App\DTOs;

use App\Http\Requests\ItemIndexRequest;

/**
 * DTO for item search using Meilisearch.
 * Holds pagination, sorting, and Meilisearch filter parameters.
 */
final readonly class ItemSearchDTO
{
    public function __construct(
        public ?string $searchQuery,
        public int $perPage,
        public int $page,
        public string $sortBy,
        public string $sortDirection,
        public ?string $meilisearchFilter = null,
    ) {}

    public static function fromRequest(ItemIndexRequest $request): self
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
