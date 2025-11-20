<?php

namespace App\Listeners;

use App\Events\ModelImported;
use Illuminate\Support\Facades\Cache;

class ClearRequestValidationCache
{
    /**
     * Handle the event.
     */
    public function handle(ModelImported $event): void
    {
        // Clear all request validation caches
        // Only use tags if the cache store supports them (redis, memcached, dynamodb)
        try {
            Cache::tags(['request_validation'])->flush();
        } catch (\BadMethodCallException $e) {
            // Cache store doesn't support tagging, clear entire cache instead
            Cache::flush();
        }
    }
}
