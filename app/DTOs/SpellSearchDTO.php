<?php

namespace App\DTOs;

use App\Http\Requests\SpellIndexRequest;

/**
 * Data Transfer Object for Spell search parameters
 */
final readonly class SpellSearchDTO
{
    public function __construct(
        public ?string $searchQuery,
        public int $perPage,
        public array $filters,
        public string $sortBy,
        public string $sortDirection,
    ) {}

    public static function fromRequest(SpellIndexRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            searchQuery: $validated['q'] ?? null,
            perPage: $validated['per_page'] ?? 15,
            filters: [
                'search' => $validated['search'] ?? null,
                'level' => $validated['level'] ?? null,
                'school' => $validated['school'] ?? null,
                'concentration' => $validated['concentration'] ?? null,
                'ritual' => $validated['ritual'] ?? null,
            ],
            sortBy: $validated['sort_by'] ?? 'name',
            sortDirection: $validated['sort_direction'] ?? 'asc',
        );
    }
}
