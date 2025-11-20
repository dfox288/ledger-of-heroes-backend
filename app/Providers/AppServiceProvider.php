<?php

namespace App\Providers;

use App\Events\ModelImported;
use App\Listeners\ClearRequestValidationCache;
use App\Models\Background;
use App\Models\CharacterClass;
use App\Models\Feat;
use App\Models\Item;
use App\Models\Race;
use App\Models\Spell;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register event listeners
        Event::listen(
            ModelImported::class,
            ClearRequestValidationCache::class
        );

        // Custom route model binding that supports both ID and slug
        Route::bind('spell', function ($value) {
            return is_numeric($value)
                ? Spell::findOrFail($value)
                : Spell::where('slug', $value)->firstOrFail();
        });

        Route::bind('race', function ($value) {
            return is_numeric($value)
                ? Race::findOrFail($value)
                : Race::where('slug', $value)->firstOrFail();
        });

        Route::bind('background', function ($value) {
            return is_numeric($value)
                ? Background::findOrFail($value)
                : Background::where('slug', $value)->firstOrFail();
        });

        Route::bind('class', function ($value) {
            return is_numeric($value)
                ? CharacterClass::findOrFail($value)
                : CharacterClass::where('slug', $value)->firstOrFail();
        });

        Route::bind('item', function ($value) {
            return is_numeric($value)
                ? Item::findOrFail($value)
                : Item::where('slug', $value)->firstOrFail();
        });

        Route::bind('feat', function ($value) {
            return is_numeric($value)
                ? Feat::findOrFail($value)
                : Feat::where('slug', $value)->firstOrFail();
        });
    }
}
