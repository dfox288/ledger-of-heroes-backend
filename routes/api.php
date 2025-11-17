<?php

use App\Http\Controllers\Api\SourceController;
use App\Http\Controllers\Api\SpellSchoolController;
use App\Http\Controllers\Api\DamageTypeController;
use App\Http\Controllers\Api\SizeController;
use App\Http\Controllers\Api\AbilityScoreController;
use App\Http\Controllers\Api\SkillController;
use App\Http\Controllers\Api\ItemTypeController;
use App\Http\Controllers\Api\ItemPropertyController;
use Illuminate\Support\Facades\Route;

// Temporary test route for CORS testing
Route::get('/test', function () {
    return response()->json(['message' => 'CORS test']);
});

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
});
