<?php

namespace App\DTOs;

use App\Http\Requests\BackgroundIndexRequest;

/**
 * Data Transfer Object for Background search parameters
 *
 * Decouples the service layer from HTTP concerns by providing
 * a framework-agnostic way to pass search parameters.
 */
final readonly class BackgroundSearchDTO
{
    /**
     * @param  string|null  $searchQuery  Full-text search query (Scout or MySQL LIKE)
     * @param  int  $perPage  Number of results per page (1-100)
     * @param  array<string, mixed>  $filters  Additional filters (grants_proficiency, grants_skill, etc.)
     * @param  string  $sortBy  Column to sort by
     * @param  string  $sortDirection  Sort direction (asc/desc)
     */
    public function __construct(
        public ?string $searchQuery,
        public int $perPage,
        public array $filters,
        public string $sortBy,
        public string $sortDirection,
    ) {}

    /**
     * Create DTO from validated Form Request data
     */
    public static function fromRequest(BackgroundIndexRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            searchQuery: $validated['q'] ?? null,
            perPage: $validated['per_page'] ?? 15,
            filters: [
                'search' => $validated['search'] ?? null,
                'grants_proficiency' => $validated['grants_proficiency'] ?? null,
                'grants_skill' => $validated['grants_skill'] ?? null,
                'speaks_language' => $validated['speaks_language'] ?? null,
                'language_choice_count' => $validated['language_choice_count'] ?? null,
                'grants_languages' => $validated['grants_languages'] ?? null,
            ],
            sortBy: $validated['sort_by'] ?? 'name',
            sortDirection: $validated['sort_direction'] ?? 'asc',
        );
    }
}
