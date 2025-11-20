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
        Cache::tags(['request_validation'])->flush();
    }
}
