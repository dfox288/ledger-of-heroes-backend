<?php

namespace App\DTOs;

use App\Http\Requests\RaceIndexRequest;

final readonly class RaceSearchDTO
{
    public function __construct(
        public ?string $searchQuery,
        public int $perPage,
        public array $filters,
        public string $sortBy,
        public string $sortDirection,
    ) {}

    public static function fromRequest(RaceIndexRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            searchQuery: $validated['q'] ?? null,
            perPage: $validated['per_page'] ?? 15,
            filters: [
                'search' => $validated['search'] ?? null,
                'size' => $validated['size'] ?? null,
                'grants_proficiency' => $validated['grants_proficiency'] ?? null,
                'grants_skill' => $validated['grants_skill'] ?? null,
                'grants_proficiency_type' => $validated['grants_proficiency_type'] ?? null,
                'speaks_language' => $validated['speaks_language'] ?? null,
                'language_choice_count' => $validated['language_choice_count'] ?? null,
                'grants_languages' => $validated['grants_languages'] ?? null,
            ],
            sortBy: $validated['sort_by'] ?? 'name',
            sortDirection: $validated['sort_direction'] ?? 'asc',
        );
    }
}
