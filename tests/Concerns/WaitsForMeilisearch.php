<?php

namespace Tests\Concerns;

use Illuminate\Database\Eloquent\Model;
use Meilisearch\Client;

/**
 * Trait for waiting on Meilisearch indexing operations in tests.
 *
 * Replaces arbitrary sleep(1) calls with intelligent polling that:
 * - Waits only as long as necessary (typically 50-200ms vs 1000ms)
 * - Has configurable timeout to prevent infinite waits
 * - Provides clear error messages on timeout
 *
 * Usage:
 *   $spell->searchable();
 *   $this->waitForMeilisearch($spell);
 *
 * Or for multiple models:
 *   $this->waitForMeilisearchModels([$spell1, $spell2, $spell3]);
 */
trait WaitsForMeilisearch
{
    /**
     * Wait for a single model to be indexed in Meilisearch.
     *
     * @param  Model  $model  The model that was just indexed
     * @param  int  $timeoutMs  Maximum time to wait in milliseconds
     * @param  int  $pollIntervalMs  Time between checks in milliseconds
     */
    protected function waitForMeilisearch(Model $model, int $timeoutMs = 5000, int $pollIntervalMs = 50): void
    {
        $this->waitForMeilisearchModels([$model], $timeoutMs, $pollIntervalMs);
    }

    /**
     * Wait for multiple models to be indexed in Meilisearch.
     *
     * @param  array<Model>  $models  The models that were just indexed
     * @param  int  $timeoutMs  Maximum time to wait in milliseconds
     * @param  int  $pollIntervalMs  Time between checks in milliseconds
     */
    protected function waitForMeilisearchModels(array $models, int $timeoutMs = 5000, int $pollIntervalMs = 50): void
    {
        if (empty($models)) {
            return;
        }

        $client = app(Client::class);
        $startTime = microtime(true) * 1000;

        // Group models by index
        $modelsByIndex = [];
        foreach ($models as $model) {
            $indexName = $model->searchableAs();
            $modelsByIndex[$indexName][$model->getScoutKey()] = $model;
        }

        // Wait for each index to have all expected documents
        foreach ($modelsByIndex as $indexName => $indexModels) {
            $expectedIds = array_keys($indexModels);

            while (true) {
                $elapsed = (microtime(true) * 1000) - $startTime;

                if ($elapsed >= $timeoutMs) {
                    $this->fail(sprintf(
                        'Meilisearch indexing timeout after %dms. Index: %s, waiting for IDs: %s',
                        $timeoutMs,
                        $indexName,
                        implode(', ', $expectedIds)
                    ));
                }

                try {
                    $index = $client->index($indexName);
                    $allFound = true;

                    foreach ($expectedIds as $id) {
                        try {
                            $index->getDocument($id);
                        } catch (\Meilisearch\Exceptions\ApiException $e) {
                            // Document not found yet
                            $allFound = false;
                            break;
                        }
                    }

                    if ($allFound) {
                        return; // All documents found, we're done
                    }
                } catch (\Exception $e) {
                    // Index might not exist yet, keep waiting
                }

                usleep($pollIntervalMs * 1000);
            }
        }
    }

    /**
     * Wait for Meilisearch to process all pending tasks for an index.
     *
     * Use this when you need to ensure all background indexing is complete,
     * not just for specific documents.
     *
     * @param  string  $indexName  The index name to wait for
     * @param  int  $timeoutMs  Maximum time to wait in milliseconds
     */
    protected function waitForMeilisearchIndex(string $indexName, int $timeoutMs = 10000): void
    {
        $client = app(Client::class);
        $startTime = microtime(true) * 1000;

        while (true) {
            $elapsed = (microtime(true) * 1000) - $startTime;

            if ($elapsed >= $timeoutMs) {
                $this->fail(sprintf(
                    'Meilisearch task processing timeout after %dms for index: %s',
                    $timeoutMs,
                    $indexName
                ));
            }

            try {
                // Get pending/processing tasks for this index using TasksQuery
                $tasksQuery = new \Meilisearch\Contracts\TasksQuery();
                $tasksQuery->setIndexUids([$indexName]);
                $tasksQuery->setStatuses(['enqueued', 'processing']);

                $tasks = $client->getTasks($tasksQuery);

                if ($tasks->getTotal() === 0) {
                    return; // No pending tasks, we're done
                }
            } catch (\Exception $e) {
                // Keep waiting
            }

            usleep(100 * 1000); // 100ms between checks
        }
    }
}
