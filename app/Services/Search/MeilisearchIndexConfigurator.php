<?php

namespace App\Services\Search;

use App\Models\Background;
use App\Models\CharacterClass;
use App\Models\Feat;
use App\Models\Item;
use App\Models\Monster;
use App\Models\OptionalFeature;
use App\Models\Race;
use App\Models\Spell;
use MeiliSearch\Client;

class MeilisearchIndexConfigurator
{
    public function __construct(
        private Client $client
    ) {}

    /**
     * Configure a Meilisearch index using the model's searchableOptions()
     *
     * This eliminates duplication by reading configuration directly from the model,
     * making the model's searchableOptions() the single source of truth.
     *
     * @param  class-string  $modelClass  Fully qualified model class name (e.g., Spell::class)
     *
     * @throws \Exception If model doesn't have searchableOptions() method
     */
    private function configureIndexFromModel(string $modelClass): void
    {
        // Instantiate model
        $model = new $modelClass;

        // Verify model has searchableOptions() method
        if (! method_exists($model, 'searchableOptions')) {
            throw new \Exception("Model {$modelClass} must have searchableOptions() method");
        }

        // Get index name (respects Scout prefix for testing)
        $indexName = $model->searchableAs();
        $index = $this->client->index($indexName);

        // Get configuration from model
        $options = $model->searchableOptions();

        // Apply searchable attributes (fields to search in)
        if (isset($options['searchableAttributes']) && is_array($options['searchableAttributes'])) {
            $index->updateSearchableAttributes($options['searchableAttributes']);
        }

        // Apply filterable attributes (fields that can be filtered)
        if (isset($options['filterableAttributes']) && is_array($options['filterableAttributes'])) {
            $index->updateFilterableAttributes($options['filterableAttributes']);
        }

        // Apply sortable attributes (fields that can be sorted)
        if (isset($options['sortableAttributes']) && is_array($options['sortableAttributes'])) {
            $index->updateSortableAttributes($options['sortableAttributes']);
        }
    }

    public function configureSpellsIndex(): void
    {
        $this->configureIndexFromModel(Spell::class);
    }

    public function configureItemsIndex(): void
    {
        $this->configureIndexFromModel(Item::class);
    }

    public function configureRacesIndex(): void
    {
        $this->configureIndexFromModel(Race::class);
    }

    public function configureClassesIndex(): void
    {
        $this->configureIndexFromModel(CharacterClass::class);
    }

    public function configureBackgroundsIndex(): void
    {
        $this->configureIndexFromModel(Background::class);
    }

    public function configureFeatsIndex(): void
    {
        $this->configureIndexFromModel(Feat::class);
    }

    public function configureMonstersIndex(): void
    {
        $this->configureIndexFromModel(Monster::class);
    }

    public function configureOptionalFeaturesIndex(): void
    {
        $this->configureIndexFromModel(OptionalFeature::class);
    }
}
