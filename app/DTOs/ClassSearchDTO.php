<?php

namespace App\DTOs;

use App\Http\Requests\ClassIndexRequest;

final readonly class ClassSearchDTO
{
    public function __construct(
        public ?string $searchQuery,
        public int $perPage,
        public array $filters,
        public string $sortBy,
        public string $sortDirection,
    ) {}

    public static function fromRequest(ClassIndexRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            searchQuery: $validated['q'] ?? null,
            perPage: $validated['per_page'] ?? 15,
            filters: [
                'search' => $validated['search'] ?? null,
                'base_only' => $validated['base_only'] ?? null,
                'grants_proficiency' => $validated['grants_proficiency'] ?? null,
                'grants_skill' => $validated['grants_skill'] ?? null,
                'grants_saving_throw' => $validated['grants_saving_throw'] ?? null,
                'spells' => $validated['spells'] ?? null,
                'spells_operator' => $validated['spells_operator'] ?? null,
                'spell_level' => $validated['spell_level'] ?? null,
            ],
            sortBy: $validated['sort_by'] ?? 'name',
            sortDirection: $validated['sort_direction'] ?? 'asc',
        );
    }
}
