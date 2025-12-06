<?php

namespace App\Providers;

use App\Events\CharacterUpdated;
use App\Events\ModelImported;
use App\Listeners\ClearRequestValidationCache;
use App\Listeners\InvalidateCharacterCache;
use App\Listeners\PopulateCharacterAbilities;
use App\Models\AbilityScore;
use App\Models\Background;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\Condition;
use App\Models\DamageType;
use App\Models\Feat;
use App\Models\Item;
use App\Models\Language;
use App\Models\Monster;
use App\Models\ProficiencyType;
use App\Models\Race;
use App\Models\Skill;
use App\Models\Spell;
use App\Models\SpellSchool;
use App\Observers\CharacterObserver;
use App\Services\CharacterChoiceService;
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
        // Bind Meilisearch Client
        $this->app->singleton(\MeiliSearch\Client::class, function ($app) {
            return new \MeiliSearch\Client(
                config('scout.meilisearch.host'),
                config('scout.meilisearch.key')
            );
        });

        // Bind CharacterChoiceService
        $this->app->singleton(CharacterChoiceService::class, function ($app) {
            $service = new CharacterChoiceService;

            // Handlers will be registered here as they are implemented
            // Example (uncomment when handler exists):
            // $service->registerHandler($app->make(\App\Services\ChoiceHandlers\ProficiencyChoiceHandler::class));

            return $service;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register model observers
        Character::observe(CharacterObserver::class);

        // Register event listeners
        Event::listen(
            ModelImported::class,
            ClearRequestValidationCache::class
        );

        Event::listen(
            CharacterUpdated::class,
            InvalidateCharacterCache::class
        );

        Event::listen(
            CharacterUpdated::class,
            PopulateCharacterAbilities::class
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

        Route::bind('monster', function ($value) {
            return is_numeric($value)
                ? Monster::findOrFail($value)
                : Monster::where('slug', $value)->firstOrFail();
        });

        Route::bind('condition', function ($value) {
            return is_numeric($value)
                ? Condition::findOrFail($value)
                : Condition::where('slug', $value)->firstOrFail();
        });

        Route::bind('spellSchool', function ($value) {
            return is_numeric($value)
                ? SpellSchool::findOrFail($value)
                : SpellSchool::where('code', $value)
                    ->orWhere('slug', $value)
                    ->firstOrFail();
        });

        Route::bind('damageType', function ($value) {
            if (is_numeric($value)) {
                return DamageType::findOrFail($value);
            }

            // Try code first (case-sensitive)
            $damageType = DamageType::where('code', $value)->first();
            if ($damageType) {
                return $damageType;
            }

            // Try name (case-insensitive)
            return DamageType::whereRaw('LOWER(name) = ?', [strtolower($value)])->firstOrFail();
        });

        Route::bind('abilityScore', function ($value) {
            if (is_numeric($value)) {
                return AbilityScore::findOrFail($value);
            }

            // Try code first (e.g., "DEX", "STR")
            $abilityScore = AbilityScore::where('code', $value)->first();
            if ($abilityScore) {
                return $abilityScore;
            }

            // Try name (case-insensitive, e.g., "dexterity")
            return AbilityScore::whereRaw('LOWER(name) = ?', [strtolower($value)])->firstOrFail();
        });

        Route::bind('language', function ($value) {
            if (is_numeric($value)) {
                return Language::findOrFail($value);
            }

            // Try slug (e.g., "elvish", "common", "thieves-cant")
            return Language::where('slug', $value)->firstOrFail();
        });

        Route::bind('proficiencyType', function ($value) {
            if (is_numeric($value)) {
                return ProficiencyType::findOrFail($value);
            }

            // Try name (case-insensitive, e.g., "Longsword", "Stealth", "Heavy Armor")
            return ProficiencyType::whereRaw('LOWER(name) = ?', [strtolower($value)])->firstOrFail();
        });

        Route::bind('skill', function ($value) {
            if (is_numeric($value)) {
                return Skill::findOrFail($value);
            }

            // Try slug (e.g., "acrobatics", "animal-handling", "sleight-of-hand")
            return Skill::where('slug', $value)->firstOrFail();
        });
    }
}
