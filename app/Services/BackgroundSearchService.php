<?php

namespace App\Services;

use App\Models\Background;

/**
 * Service for searching and filtering D&D backgrounds
 *
 * Handles Scout/Meilisearch search with MySQL fallback, applies filters,
 * and returns paginated results. Keeps controllers thin by extracting
 * all query-building logic into this testable service layer.
 */
final class BackgroundSearchService extends AbstractSearchService
{
    /**
     * Relationships for index/list endpoints (lightweight)
     */
    private const INDEX_RELATIONSHIPS = [
        'sources.source',
    ];

    /**
     * Relationships for show/detail endpoints (comprehensive)
     */
    private const SHOW_RELATIONSHIPS = [
        'sources.source',
        'traits.dataTables.entries',
        'proficiencies.skill.abilityScore',
        'proficiencies.proficiencyType',
        'proficiencies.item',
        'languages.language',
        'languages.conditionLanguage',
        'equipment.item.contents.item',
        'tags',
    ];

    /**
     * Get the fully qualified model class name
     *
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected function getModelClass(): string
    {
        return Background::class;
    }

    /**
     * Get relationships for index/list endpoints
     */
    public function getIndexRelationships(): array
    {
        return self::INDEX_RELATIONSHIPS;
    }

    /**
     * Get relationships for show/detail endpoints
     */
    public function getShowRelationships(): array
    {
        return self::SHOW_RELATIONSHIPS;
    }

    /**
     * Build Scout search query for full-text search
     *
     * NOTE: MySQL filtering has been removed. Use Meilisearch ?filter= parameter instead.
     *
     * Examples:
     * - ?filter=tag_slugs IN [criminal]
     * - ?filter=tag_slugs IN [noble, outlander]
     * - ?filter=source_codes IN [PHB]
     *
     * @param  string|object  $searchQueryOrDto  Search query string or DTO object
     */
    public function buildScoutQuery(string|object $searchQueryOrDto): \Laravel\Scout\Builder
    {
        $searchQuery = is_string($searchQueryOrDto) ? $searchQueryOrDto : ($searchQueryOrDto->searchQuery ?? '');

        return Background::search($searchQuery);
    }
}
