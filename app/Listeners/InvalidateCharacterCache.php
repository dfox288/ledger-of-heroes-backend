<?php

namespace App\Listeners;

use App\Events\CharacterUpdated;
use Illuminate\Support\Facades\Cache;

class InvalidateCharacterCache
{
    /**
     * Handle the event.
     */
    public function handle(CharacterUpdated $event): void
    {
        Cache::forget("character:{$event->character->id}:stats");
    }
}
