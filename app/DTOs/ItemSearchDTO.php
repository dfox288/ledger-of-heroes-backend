<?php

namespace App\DTOs;

use App\Http\Requests\ItemIndexRequest;

final readonly class ItemSearchDTO
{
    public function __construct(
        public ?string $searchQuery,
        public int $perPage,
        public array $filters,
        public string $sortBy,
        public string $sortDirection,
    ) {}

    public static function fromRequest(ItemIndexRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            searchQuery: $validated['q'] ?? null,
            perPage: $validated['per_page'] ?? 15,
            filters: [
                'search' => $validated['search'] ?? null,
                'item_type_id' => $validated['item_type_id'] ?? null,
                'rarity' => $validated['rarity'] ?? null,
                'is_magic' => $validated['is_magic'] ?? null,
                'requires_attunement' => $validated['requires_attunement'] ?? null,
                'min_strength' => $validated['min_strength'] ?? null,
                'has_prerequisites' => $validated['has_prerequisites'] ?? null,
            ],
            sortBy: $validated['sort_by'] ?? 'name',
            sortDirection: $validated['sort_direction'] ?? 'asc',
        );
    }
}
