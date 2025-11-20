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
            'level',
            'school_code',
            'concentration',
            'ritual',
            'source_codes',
            'class_slugs',
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
}
