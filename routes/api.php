<?php

use App\Http\Controllers\Api\AbilityScoreController;
use App\Http\Controllers\Api\BackgroundController;
use App\Http\Controllers\Api\ConditionController;
use App\Http\Controllers\Api\DamageTypeController;
use App\Http\Controllers\Api\FeatController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\ItemPropertyController;
use App\Http\Controllers\Api\ItemTypeController;
use App\Http\Controllers\Api\LanguageController;
use App\Http\Controllers\Api\ProficiencyTypeController;
use App\Http\Controllers\Api\RaceController;
use App\Http\Controllers\Api\SizeController;
use App\Http\Controllers\Api\SkillController;
use App\Http\Controllers\Api\SourceController;
use App\Http\Controllers\Api\SpellController;
use App\Http\Controllers\Api\SpellSchoolController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Lookup tables
    Route::apiResource('sources', SourceController::class)->only(['index', 'show']);
    Route::apiResource('spell-schools', SpellSchoolController::class)->only(['index', 'show']);
    Route::apiResource('damage-types', DamageTypeController::class)->only(['index', 'show']);
    Route::apiResource('sizes', SizeController::class)->only(['index', 'show']);
    Route::apiResource('ability-scores', AbilityScoreController::class)->only(['index', 'show']);
    Route::apiResource('skills', SkillController::class)->only(['index', 'show']);
    Route::apiResource('item-types', ItemTypeController::class)->only(['index', 'show']);
    Route::apiResource('item-properties', ItemPropertyController::class)->only(['index', 'show']);
    Route::apiResource('conditions', ConditionController::class)->only(['index', 'show']);
    Route::apiResource('proficiency-types', ProficiencyTypeController::class)->only(['index', 'show']);
    Route::apiResource('languages', LanguageController::class)->only(['index', 'show']);

    // Spells
    Route::apiResource('spells', SpellController::class)->only(['index', 'show']);

    // Races
    Route::apiResource('races', RaceController::class)->only(['index', 'show']);

    // Backgrounds
    Route::apiResource('backgrounds', BackgroundController::class)->only(['index', 'show']);

    // Items
    Route::apiResource('items', ItemController::class)->only(['index', 'show']);

    // Feats
    Route::apiResource('feats', FeatController::class)->only(['index', 'show']);
});
