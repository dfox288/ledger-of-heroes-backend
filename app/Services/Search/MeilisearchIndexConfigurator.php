<?php

namespace App\Services\Search;

use App\Models\Background;
use App\Models\CharacterClass;
use App\Models\Feat;
use App\Models\Item;
use App\Models\Monster;
use App\Models\Race;
use App\Models\Spell;
use MeiliSearch\Client;

class MeilisearchIndexConfigurator
{
    public function __construct(
        private Client $client
    ) {}

    public function configureSpellsIndex(): void
    {
        // Use model's searchableAs() to respect Scout prefix (test_ for testing, none for production)
        $indexName = (new Spell)->searchableAs();
        $index = $this->client->index($indexName);

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
        $indexName = (new Item)->searchableAs();
        $index = $this->client->index($indexName);

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
        $indexName = (new Race)->searchableAs();
        $index = $this->client->index($indexName);

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
            'tag_slugs',
        ]);

        // Sortable attributes
        $index->updateSortableAttributes([
            'name',
            'speed',
        ]);
    }

    public function configureClassesIndex(): void
    {
        $indexName = (new CharacterClass)->searchableAs();
        $index = $this->client->index($indexName);

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
            'tag_slugs',
        ]);

        // Sortable attributes
        $index->updateSortableAttributes([
            'name',
            'hit_die',
        ]);
    }

    public function configureBackgroundsIndex(): void
    {
        $indexName = (new Background)->searchableAs();
        $index = $this->client->index($indexName);

        // Searchable attributes
        $index->updateSearchableAttributes([
            'name',
            'sources',
        ]);

        // Filterable attributes
        $index->updateFilterableAttributes([
            'source_codes',
            'tag_slugs',
        ]);

        // Sortable attributes
        $index->updateSortableAttributes([
            'name',
        ]);
    }

    public function configureFeatsIndex(): void
    {
        $indexName = (new Feat)->searchableAs();
        $index = $this->client->index($indexName);

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
            'tag_slugs',
        ]);

        // Sortable attributes
        $index->updateSortableAttributes([
            'name',
        ]);
    }

    public function configureMonstersIndex(): void
    {
        $indexName = (new Monster)->searchableAs();
        $index = $this->client->index($indexName);

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
