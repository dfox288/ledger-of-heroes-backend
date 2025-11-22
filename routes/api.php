<?php

use App\Http\Controllers\Api\AbilityScoreController;
use App\Http\Controllers\Api\BackgroundController;
use App\Http\Controllers\Api\ClassController;
use App\Http\Controllers\Api\ConditionController;
use App\Http\Controllers\Api\DamageTypeController;
use App\Http\Controllers\Api\FeatController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\ItemPropertyController;
use App\Http\Controllers\Api\ItemTypeController;
use App\Http\Controllers\Api\LanguageController;
use App\Http\Controllers\Api\MonsterController;
use App\Http\Controllers\Api\ProficiencyTypeController;
use App\Http\Controllers\Api\RaceController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SizeController;
use App\Http\Controllers\Api\SkillController;
use App\Http\Controllers\Api\SourceController;
use App\Http\Controllers\Api\SpellController;
use App\Http\Controllers\Api\SpellSchoolController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Global search
    Route::get('/search', SearchController::class)->name('search');

    // Lookup tables
    Route::apiResource('sources', SourceController::class)->only(['index', 'show']);
    Route::apiResource('spell-schools', SpellSchoolController::class)->only(['index', 'show']);

    // Spell school spell list endpoint
    Route::get('spell-schools/{spellSchool}/spells', [SpellSchoolController::class, 'spells'])
        ->name('spell-schools.spells');

    // Damage type spell list endpoint (must be before apiResource)
    Route::get('damage-types/{damageType}/spells', [DamageTypeController::class, 'spells'])
        ->name('damage-types.spells');

    // Damage type item list endpoint
    Route::get('damage-types/{damageType}/items', [DamageTypeController::class, 'items'])
        ->name('damage-types.items');

    Route::apiResource('damage-types', DamageTypeController::class)->only(['index', 'show']);

    Route::apiResource('sizes', SizeController::class)->only(['index', 'show']);
    Route::apiResource('ability-scores', AbilityScoreController::class)->only(['index', 'show']);

    // Ability score spell list endpoint
    Route::get('ability-scores/{abilityScore}/spells', [AbilityScoreController::class, 'spells'])
        ->name('ability-scores.spells');

    Route::apiResource('skills', SkillController::class)->only(['index', 'show']);
    Route::apiResource('item-types', ItemTypeController::class)->only(['index', 'show']);
    Route::apiResource('item-properties', ItemPropertyController::class)->only(['index', 'show']);
    Route::apiResource('conditions', ConditionController::class)->only(['index', 'show']);

    // Condition spell list endpoint
    Route::get('conditions/{condition}/spells', [ConditionController::class, 'spells'])
        ->name('conditions.spells');

    // Condition monster list endpoint
    Route::get('conditions/{condition}/monsters', [ConditionController::class, 'monsters'])
        ->name('conditions.monsters');

    Route::apiResource('proficiency-types', ProficiencyTypeController::class)->only(['index', 'show']);
    Route::apiResource('languages', LanguageController::class)->only(['index', 'show']);

    // Spells
    Route::apiResource('spells', SpellController::class)->only(['index', 'show']);

    // Spell reverse relationship endpoints
    Route::get('spells/{spell}/classes', [SpellController::class, 'classes'])
        ->name('spells.classes');
    Route::get('spells/{spell}/monsters', [SpellController::class, 'monsters'])
        ->name('spells.monsters');
    Route::get('spells/{spell}/items', [SpellController::class, 'items'])
        ->name('spells.items');
    Route::get('spells/{spell}/races', [SpellController::class, 'races'])
        ->name('spells.races');

    // Races
    Route::apiResource('races', RaceController::class)->only(['index', 'show']);

    // Race spell list endpoint
    Route::get('races/{race}/spells', [RaceController::class, 'spells'])
        ->name('races.spells');

    // Backgrounds
    Route::apiResource('backgrounds', BackgroundController::class)->only(['index', 'show']);

    // Items
    Route::apiResource('items', ItemController::class)->only(['index', 'show']);

    // Feats
    Route::apiResource('feats', FeatController::class)->only(['index', 'show']);

    // Classes
    Route::apiResource('classes', ClassController::class)->only(['index', 'show']);

    // Class spell list endpoint
    Route::get('classes/{class}/spells', [ClassController::class, 'spells'])
        ->name('classes.spells');

    // Monsters
    Route::apiResource('monsters', MonsterController::class)->only(['index', 'show']);

    // Monster spell list endpoint
    Route::get('monsters/{monster}/spells', [MonsterController::class, 'spells'])
        ->name('monsters.spells');
});
