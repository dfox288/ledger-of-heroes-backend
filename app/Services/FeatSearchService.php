<?php

namespace App\Services;

use App\Models\Feat;

final class FeatSearchService extends AbstractSearchService
{
    /**
     * Relationships for index/list endpoints (lightweight)
     */
    private const INDEX_RELATIONSHIPS = [
        'sources.source',
        'modifiers.abilityScore',
        'modifiers.skill',
        'proficiencies.skill.abilityScore',
        'proficiencies.proficiencyType',
        'proficiencies.item',
        'conditions.condition',
        'prerequisites.prerequisite',
        'languages.language',
    ];

    /**
     * Relationships for show/detail endpoints (comprehensive)
     * Uses entitySpellRecords for fixed spell grants, spellChoices for choice-based grants
     */
    private const SHOW_RELATIONSHIPS = [
        'sources.source',
        'modifiers.abilityScore',
        'modifiers.skill',
        'modifiers.damageType',
        'proficiencies.skill.abilityScore',
        'proficiencies.proficiencyType',
        'proficiencies.item',
        'conditions.condition',
        'prerequisites.prerequisite',
        'tags',
        'entitySpellRecords.spell',
        'entitySpellRecords.abilityScore',
        'spellChoices',
        'languages.language',
        'nonEquipmentChoices',
    ];

    /**
     * Get the fully qualified model class name
     *
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected function getModelClass(): string
    {
        return Feat::class;
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
     * - ?filter=tag_slugs IN [combat]
     * - ?filter=tag_slugs IN [magic, skill-improvement]
     * - ?filter=source_codes IN [PHB, XGE]
     * - ?filter=tag_slugs IN [combat] AND source_codes IN [PHB]
     *
     * @param  string|object  $searchQueryOrDto  Search query string or DTO object
     */
    public function buildScoutQuery(string|object $searchQueryOrDto): \Laravel\Scout\Builder
    {
        $searchQuery = is_string($searchQueryOrDto) ? $searchQueryOrDto : ($searchQueryOrDto->searchQuery ?? '');

        return Feat::search($searchQuery);
    }
}
