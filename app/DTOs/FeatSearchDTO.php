<?php

namespace App\DTOs;

use App\Http\Requests\FeatIndexRequest;

final readonly class FeatSearchDTO
{
    public function __construct(
        public ?string $searchQuery,
        public int $perPage,
        public array $filters,
        public string $sortBy,
        public string $sortDirection,
    ) {}

    public static function fromRequest(FeatIndexRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            searchQuery: $validated['q'] ?? null,
            perPage: $validated['per_page'] ?? 15,
            filters: [
                'search' => $validated['search'] ?? null,
                'prerequisite_race' => $validated['prerequisite_race'] ?? null,
                'prerequisite_ability' => $validated['prerequisite_ability'] ?? null,
                'min_value' => $validated['min_value'] ?? null,
                'has_prerequisites' => $validated['has_prerequisites'] ?? null,
                'grants_proficiency' => $validated['grants_proficiency'] ?? null,
                'prerequisite_proficiency' => $validated['prerequisite_proficiency'] ?? null,
                'grants_skill' => $validated['grants_skill'] ?? null,
            ],
            sortBy: $validated['sort_by'] ?? 'name',
            sortDirection: $validated['sort_direction'] ?? 'asc',
        );
    }
}
