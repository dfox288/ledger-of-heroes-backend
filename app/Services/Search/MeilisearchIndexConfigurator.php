<?php

namespace App\Services\Search;

use MeiliSearch\Client;

class MeilisearchIndexConfigurator
{
    public function __construct(
        private Client $client
    ) {}

    public function configureSpellsIndex(): void
    {
        $index = $this->client->index('spells');

        // Configure searchable attributes (fields that will be searched)
        $index->updateSearchableAttributes([
            'name',
            'description',
            'at_higher_levels',
            'school_name',
            'sources',
            'classes',
        ]);

        // Configure filterable attributes (fields that can be used in filters)
        $index->updateFilterableAttributes([
            'id',
            'level',
            'school_code',
            'school_name',
            'concentration',
            'ritual',
            'source_codes',
            'class_slugs',
            'tag_slugs',
        ]);

        // Configure sortable attributes (fields that can be used for sorting)
        $index->updateSortableAttributes([
            'name',
            'level',
        ]);
    }

    public function configureItemsIndex(): void
    {
        $index = $this->client->index('items');

        // Searchable attributes
        $index->updateSearchableAttributes([
            'name',
            'description',
            'type_name',
            'sources',
            'damage_type',
        ]);

        // Filterable attributes
        $index->updateFilterableAttributes([
            'type_code',
            'rarity',
            'is_magic',
            'requires_attunement',
            'source_codes',
            'tag_slugs',
        ]);

        // Sortable attributes
        $index->updateSortableAttributes([
            'name',
            'rarity',
            'cost_cp',
            'weight',
        ]);
    }

    public function configureRacesIndex(): void
    {
        $index = $this->client->index('races');

        // Searchable attributes
        $index->updateSearchableAttributes([
            'name',
            'size_name',
            'sources',
            'parent_race_name',
        ]);

        // Filterable attributes
        $index->updateFilterableAttributes([
            'size_code',
            'speed',
            'source_codes',
            'is_subrace',
        ]);

        // Sortable attributes
        $index->updateSortableAttributes([
            'name',
            'speed',
        ]);
    }

    public function configureClassesIndex(): void
    {
        $index = $this->client->index('classes');

        // Searchable attributes
        $index->updateSearchableAttributes([
            'name',
            'description',
            'primary_ability',
            'sources',
            'parent_class_name',
        ]);

        // Filterable attributes
        $index->updateFilterableAttributes([
            'hit_die',
            'spellcasting_ability',
            'source_codes',
            'is_subclass',
        ]);

        // Sortable attributes
        $index->updateSortableAttributes([
            'name',
            'hit_die',
        ]);
    }

    public function configureBackgroundsIndex(): void
    {
        $index = $this->client->index('backgrounds');

        // Searchable attributes
        $index->updateSearchableAttributes([
            'name',
            'sources',
        ]);

        // Filterable attributes
        $index->updateFilterableAttributes([
            'source_codes',
        ]);

        // Sortable attributes
        $index->updateSortableAttributes([
            'name',
        ]);
    }

    public function configureFeatsIndex(): void
    {
        $index = $this->client->index('feats');

        // Searchable attributes
        $index->updateSearchableAttributes([
            'name',
            'description',
            'prerequisites_text',
            'sources',
        ]);

        // Filterable attributes
        $index->updateFilterableAttributes([
            'source_codes',
        ]);

        // Sortable attributes
        $index->updateSortableAttributes([
            'name',
        ]);
    }

    public function configureMonstersIndex(): void
    {
        $index = $this->client->index('monsters_index');

        // Searchable attributes
        $index->updateSearchableAttributes([
            'name',
            'description',
            'type',
            'size_name',
            'sources',
        ]);

        // Filterable attributes
        $index->updateFilterableAttributes([
            'id',
            'type',
            'size_code',
            'alignment',
            'challenge_rating',
            'armor_class',
            'hit_points_average',
            'experience_points',
            'source_codes',
            'spell_slugs', // For fast spell filtering (1,098 relationships for 129 spellcasters)
            'tag_slugs', // For filtering by tags (e.g., fire_immune, undead, construct)
        ]);

        // Sortable attributes
        $index->updateSortableAttributes([
            'name',
            'challenge_rating',
            'armor_class',
            'hit_points_average',
            'experience_points',
        ]);
    }
}
