<?php

namespace App\Services;

use App\DTOs\OptionalFeatureSearchDTO;
use App\Models\OptionalFeature;

/**
 * Service for searching and filtering D&D optional features
 */
final class OptionalFeatureSearchService extends AbstractSearchService
{
    /**
     * Relationships for index/list endpoints (lightweight)
     */
    private const INDEX_RELATIONSHIPS = [
        'classes',
        'sources.source',
        'spellSchool',
    ];

    /**
     * Relationships for show/detail endpoints (comprehensive)
     */
    private const SHOW_RELATIONSHIPS = [
        'classes',
        'sources.source',
        'spellSchool',
        'tags',
        'prerequisites.prerequisite',
        'rolls',
    ];

    /**
     * Get the fully qualified model class name
     *
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected function getModelClass(): string
    {
        return OptionalFeature::class;
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
     * Build Scout search query for full-text search.
     *
     * Examples:
     * - ?filter=feature_type = eldritch_invocation
     * - ?filter=level_requirement <= 5
     * - ?filter=class_slugs IN [warlock]
     * - ?filter=has_spell_mechanics = true
     *
     * @param  OptionalFeatureSearchDTO|object  $dto
     */
    public function buildScoutQuery(object $dto): \Laravel\Scout\Builder
    {
        return OptionalFeature::search($dto->searchQuery ?? '');
    }
}
