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
}
