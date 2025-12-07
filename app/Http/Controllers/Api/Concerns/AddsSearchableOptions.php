<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Adds searchable_options to pagination meta for Meilisearch-enabled endpoints.
 */
trait AddsSearchableOptions
{
    /**
     * Add searchable_options to the resource collection's meta.
     *
     * @param  AnonymousResourceCollection  $collection  The resource collection
     * @param  string  $modelClass  The model class with searchableOptions() method
     */
    protected function withSearchableOptions(
        AnonymousResourceCollection $collection,
        string $modelClass
    ): AnonymousResourceCollection {
        if (! method_exists($modelClass, 'searchableOptions')) {
            return $collection;
        }

        $options = (new $modelClass)->searchableOptions();

        return $collection->additional([
            'meta' => [
                'searchable_options' => [
                    'filterable_attributes' => $options['filterableAttributes'] ?? [],
                    'sortable_attributes' => $options['sortableAttributes'] ?? [],
                ],
            ],
        ]);
    }
}
