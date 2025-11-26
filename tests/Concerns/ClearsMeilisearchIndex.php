<?php

namespace Tests\Concerns;

use Illuminate\Database\Eloquent\Model;
use Meilisearch\Client;

/**
 * Trait for clearing Meilisearch indexes in tests.
 *
 * Provides a standardized way to clear indexes before tests run,
 * ensuring test isolation when using Meilisearch for search/filter tests.
 *
 * Usage:
 *   protected function setUp(): void
 *   {
 *       parent::setUp();
 *       $this->clearMeilisearchIndex(Spell::class);
 *   }
 *
 * Or clear multiple indexes:
 *   $this->clearMeilisearchIndexes([Spell::class, Monster::class]);
 */
trait ClearsMeilisearchIndex
{
    /**
     * Clear all documents from a Meilisearch index for a given model.
     *
     * @param  string  $modelClass  The fully qualified model class name
     * @param  int  $timeoutMs  Maximum time to wait for deletion to complete
     */
    protected function clearMeilisearchIndex(string $modelClass, int $timeoutMs = 5000): void
    {
        /** @var Model $model */
        $model = new $modelClass;
        $client = app(Client::class);
        $indexName = $model->searchableAs();

        try {
            $task = $client->index($indexName)->deleteAllDocuments();
            $client->waitForTask($task['taskUid'], $timeoutMs);
        } catch (\Meilisearch\Exceptions\ApiException $e) {
            // Index may not exist yet - that's fine
            if (! str_contains($e->getMessage(), 'not found')) {
                throw $e;
            }
        }
    }

    /**
     * Clear all documents from multiple Meilisearch indexes.
     *
     * @param  array<string>  $modelClasses  Array of fully qualified model class names
     * @param  int  $timeoutMs  Maximum time to wait for each deletion
     */
    protected function clearMeilisearchIndexes(array $modelClasses, int $timeoutMs = 5000): void
    {
        foreach ($modelClasses as $modelClass) {
            $this->clearMeilisearchIndex($modelClass, $timeoutMs);
        }
    }
}
