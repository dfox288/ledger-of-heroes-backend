<?php

namespace App\DTOs;

use App\Http\Requests\MonsterIndexRequest;

final readonly class MonsterSearchDTO
{
    public function __construct(
        public ?string $searchQuery,
        public int $perPage,
        public int $page,
        public array $filters,
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
            filters: [
                'challenge_rating' => $validated['challenge_rating'] ?? null,
                'min_cr' => $validated['min_cr'] ?? null,
                'max_cr' => $validated['max_cr'] ?? null,
                'type' => $validated['type'] ?? null,
                'size' => $validated['size'] ?? null,
                'alignment' => $validated['alignment'] ?? null,
            ],
            sortBy: $validated['sort_by'] ?? 'name',
            sortDirection: $validated['sort_direction'] ?? 'asc',
            meilisearchFilter: $validated['filter'] ?? null,
        );
    }
}
