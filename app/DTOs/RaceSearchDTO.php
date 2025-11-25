<?php

namespace App\DTOs;

use App\Http\Requests\RaceIndexRequest;

/**
 * Data transfer object for race search requests.
 *
 * Encapsulates search parameters for Meilisearch-based filtering.
 */
final readonly class RaceSearchDTO
{
    public function __construct(
        public ?string $searchQuery,
        public int $page,
        public int $perPage,
        public ?string $meilisearchFilter,
        public string $sortBy,
        public string $sortDirection,
    ) {}

    public static function fromRequest(RaceIndexRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            searchQuery: $validated['q'] ?? null,
            page: $validated['page'] ?? 1,
            perPage: $validated['per_page'] ?? 15,
            meilisearchFilter: $validated['filter'] ?? null,
            sortBy: $validated['sort_by'] ?? 'name',
            sortDirection: $validated['sort_direction'] ?? 'asc',
        );
    }
}
