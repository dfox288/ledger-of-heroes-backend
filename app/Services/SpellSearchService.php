<?php

namespace App\Services;

use App\Models\Spell;

/**
 * Service for searching and filtering D&D spells
 */
final class SpellSearchService extends AbstractSearchService
{
    /**
     * Relationships for index/list endpoints (lightweight)
     */
    private const INDEX_RELATIONSHIPS = [
        'spellSchool',
        'sources.source',
        'effects.damageType',
        'classes',
    ];

    /**
     * Relationships for show/detail endpoints (comprehensive)
     */
    private const SHOW_RELATIONSHIPS = [
        'spellSchool',
        'sources.source',
        'effects.damageType',
        'classes',
        'tags',
        'savingThrows',
        'dataTables.entries',
        'monsters',
        'items',
        'races',
    ];

    /**
     * Get the fully qualified model class name
     *
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected function getModelClass(): string
    {
        return Spell::class;
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
     * - ?filter=level = 0
     * - ?filter=school = EV
     * - ?filter=concentration = true
     * - ?filter=level <= 3 AND school = EV
     */
    public function buildScoutQuery(object $dto): \Laravel\Scout\Builder
    {
        return parent::buildScoutQuery($dto);
    }
}
