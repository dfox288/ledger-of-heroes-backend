<?php

namespace App\Services;

use App\Models\Race;

final class RaceSearchService extends AbstractSearchService
{
    /**
     * Relationships for index/list endpoints (lightweight)
     * Uses entitySpellRecords for full EntitySpell pivot records
     */
    private const INDEX_RELATIONSHIPS = [
        'size',
        'sources.source',
        'proficiencies.skill',
        'proficiencies.item',
        'traits.dataTables.entries',
        'modifiers.abilityScore',
        'conditions.condition',
        'entitySpellRecords.spell',
        'entitySpellRecords.abilityScore',
        'entitySpellRecords.school',
        'entitySpellRecords.characterClass',
        'senses.sense',
        'parent',
    ];

    /**
     * Relationships for show/detail endpoints (comprehensive)
     * Uses entitySpellRecords for full EntitySpell pivot records
     */
    private const SHOW_RELATIONSHIPS = [
        'size',
        'sources.source',
        'parent.size',
        'parent.sources.source',
        'parent.proficiencies.skill.abilityScore',
        'parent.proficiencies.item',
        'parent.proficiencies.abilityScore',
        'parent.traits.dataTables.entries',
        'parent.modifiers.abilityScore',
        'parent.modifiers.skill',
        'parent.modifiers.damageType',
        'parent.languages.language',
        'parent.conditions.condition',
        'parent.entitySpellRecords.spell',
        'parent.entitySpellRecords.abilityScore',
        'parent.entitySpellRecords.school',
        'parent.entitySpellRecords.characterClass',
        'parent.senses.sense',
        'parent.tags',
        'subraces.sources.source',
        'proficiencies.skill.abilityScore',
        'proficiencies.item',
        'proficiencies.abilityScore',
        'traits.dataTables.entries',
        'modifiers.abilityScore',
        'modifiers.skill',
        'modifiers.damageType',
        'languages.language',
        'conditions.condition',
        'entitySpellRecords.spell',
        'entitySpellRecords.abilityScore',
        'entitySpellRecords.school',
        'entitySpellRecords.characterClass',
        'senses.sense',
        'tags',
    ];

    /**
     * Get the fully qualified model class name
     *
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected function getModelClass(): string
    {
        return Race::class;
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
     * - ?filter=size_code = M
     * - ?filter=speed >= 30
     * - ?filter=has_darkvision = true
     * - ?filter=spell_slugs IN [misty-step, faerie-fire]
     * - ?filter=tag_slugs IN [darkvision, fey-ancestry]
     */
    public function buildScoutQuery(object $dto): \Laravel\Scout\Builder
    {
        return parent::buildScoutQuery($dto);
    }
}
