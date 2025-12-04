<?php

use App\Http\Controllers\Api\AbilityScoreController;
use App\Http\Controllers\Api\AlignmentController;
use App\Http\Controllers\Api\ArmorTypeController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BackgroundController;
use App\Http\Controllers\Api\CharacterController;
use App\Http\Controllers\Api\ClassController;
use App\Http\Controllers\Api\ConditionController;
use App\Http\Controllers\Api\DamageTypeController;
use App\Http\Controllers\Api\FeatController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\ItemPropertyController;
use App\Http\Controllers\Api\ItemTypeController;
use App\Http\Controllers\Api\LanguageController;
use App\Http\Controllers\Api\MonsterController;
use App\Http\Controllers\Api\MonsterTypeController;
use App\Http\Controllers\Api\OptionalFeatureController;
use App\Http\Controllers\Api\OptionalFeatureTypeController;
use App\Http\Controllers\Api\ProficiencyTypeController;
use App\Http\Controllers\Api\RaceController;
use App\Http\Controllers\Api\RarityController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SizeController;
use App\Http\Controllers\Api\SkillController;
use App\Http\Controllers\Api\SourceController;
use App\Http\Controllers\Api\SpellController;
use App\Http\Controllers\Api\SpellSchoolController;
use App\Http\Controllers\Api\TagController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    /*
    |--------------------------------------------------------------------------
    | Authentication Endpoints
    |--------------------------------------------------------------------------
    |
    | Token-based authentication using Laravel Sanctum.
    | - POST /auth/login - Authenticate and receive API token
    | - POST /auth/register - Create new user account
    | - POST /auth/logout - Revoke current API token (requires auth)
    |
    */
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])->name('login');
        Route::post('/register', [AuthController::class, 'register'])->name('register');
        Route::post('/logout', [AuthController::class, 'logout'])
            ->middleware('auth:sanctum')
            ->name('logout');
    });

    // Global search
    Route::get('/search', SearchController::class)->name('search');

    /*
    |--------------------------------------------------------------------------
    | Lookup Endpoints (Reference Data)
    |--------------------------------------------------------------------------
    |
    | Small, static reference data for populating dropdowns and filters.
    | All endpoints moved under /lookups/ prefix for clear separation from
    | main entity endpoints.
    |
    */
    Route::prefix('lookups')->name('lookups.')->group(function () {
        // Sources (books)
        Route::apiResource('sources', SourceController::class)->only(['index', 'show']);

        // Spell Schools
        Route::apiResource('spell-schools', SpellSchoolController::class)->only(['index', 'show']);
        Route::get('spell-schools/{spellSchool}/spells', [SpellSchoolController::class, 'spells'])
            ->name('spell-schools.spells');

        // Damage Types
        Route::get('damage-types/{damageType}/spells', [DamageTypeController::class, 'spells'])
            ->name('damage-types.spells');
        Route::get('damage-types/{damageType}/items', [DamageTypeController::class, 'items'])
            ->name('damage-types.items');
        Route::apiResource('damage-types', DamageTypeController::class)->only(['index', 'show']);

        // Sizes
        Route::apiResource('sizes', SizeController::class)->only(['index', 'show']);
        Route::get('sizes/{size}/races', [SizeController::class, 'races'])
            ->name('sizes.races');
        Route::get('sizes/{size}/monsters', [SizeController::class, 'monsters'])
            ->name('sizes.monsters');

        // Ability Scores
        Route::apiResource('ability-scores', AbilityScoreController::class)->only(['index', 'show']);
        Route::get('ability-scores/{abilityScore}/spells', [AbilityScoreController::class, 'spells'])
            ->name('ability-scores.spells');

        // Skills
        Route::apiResource('skills', SkillController::class)->only(['index', 'show']);

        // Item Types
        Route::apiResource('item-types', ItemTypeController::class)->only(['index', 'show']);

        // Item Properties
        Route::apiResource('item-properties', ItemPropertyController::class)->only(['index', 'show']);

        // Conditions
        Route::apiResource('conditions', ConditionController::class)->only(['index', 'show']);
        Route::get('conditions/{condition}/spells', [ConditionController::class, 'spells'])
            ->name('conditions.spells');
        Route::get('conditions/{condition}/monsters', [ConditionController::class, 'monsters'])
            ->name('conditions.monsters');

        // Proficiency Types
        Route::apiResource('proficiency-types', ProficiencyTypeController::class)->only(['index', 'show']);
        Route::get('proficiency-types/{proficiencyType}/classes', [ProficiencyTypeController::class, 'classes'])
            ->name('proficiency-types.classes');
        Route::get('proficiency-types/{proficiencyType}/races', [ProficiencyTypeController::class, 'races'])
            ->name('proficiency-types.races');
        Route::get('proficiency-types/{proficiencyType}/backgrounds', [ProficiencyTypeController::class, 'backgrounds'])
            ->name('proficiency-types.backgrounds');

        // Languages
        Route::apiResource('languages', LanguageController::class)->only(['index', 'show']);
        Route::get('languages/{language}/races', [LanguageController::class, 'races'])
            ->name('languages.races');
        Route::get('languages/{language}/backgrounds', [LanguageController::class, 'backgrounds'])
            ->name('languages.backgrounds');

        // ========================================
        // Derived Lookups (no database tables)
        // ========================================

        // Tags (from Spatie tags table)
        Route::get('tags', [TagController::class, 'index'])->name('tags.index');

        // Monster Types (derived from monsters.type)
        Route::get('monster-types', [MonsterTypeController::class, 'index'])->name('monster-types.index');

        // Alignments (derived from monsters.alignment)
        Route::get('alignments', [AlignmentController::class, 'index'])->name('alignments.index');

        // Armor Types (derived from monsters.armor_type)
        Route::get('armor-types', [ArmorTypeController::class, 'index'])->name('armor-types.index');

        // Rarities (derived from items.rarity)
        Route::get('rarities', [RarityController::class, 'index'])->name('rarities.index');

        // Optional Feature Types (from OptionalFeatureType enum)
        Route::get('optional-feature-types', [OptionalFeatureTypeController::class, 'index'])->name('optional-feature-types.index');
    });

    /*
    |--------------------------------------------------------------------------
    | Entity Endpoints (Main Data)
    |--------------------------------------------------------------------------
    |
    | Large, searchable entity data (spells, monsters, items, etc.)
    | These remain at the root level for backward compatibility and
    | cleaner URLs for the main API resources.
    |
    */

    // Spells
    Route::apiResource('spells', SpellController::class)->only(['index', 'show']);
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
    Route::get('classes/{class}/spells', [ClassController::class, 'spells'])
        ->name('classes.spells');
    Route::get('classes/{class}/progression', [ClassController::class, 'progression'])
        ->name('classes.progression');

    // Monsters
    Route::apiResource('monsters', MonsterController::class)->only(['index', 'show']);
    Route::get('monsters/{monster}/spells', [MonsterController::class, 'spells'])
        ->name('monsters.spells');

    // Optional Features
    Route::get('optional-features', [OptionalFeatureController::class, 'index'])->name('optional-features.index');
    Route::get('optional-features/{optionalFeature:slug}', [OptionalFeatureController::class, 'show'])->name('optional-features.show');

    /*
    |--------------------------------------------------------------------------
    | Character Builder API
    |--------------------------------------------------------------------------
    |
    | Character creation and management endpoints for the D&D 5e Character Builder.
    | Supports wizard-style creation where fields can be filled in any order.
    |
    */
    Route::apiResource('characters', CharacterController::class);
    Route::get('characters/{character}/stats', [CharacterController::class, 'stats'])
        ->name('characters.stats');

    // Character Spell Management
    Route::prefix('characters/{character}')->name('characters.')->group(function () {
        Route::get('spells', [\App\Http\Controllers\Api\CharacterSpellController::class, 'index'])
            ->name('spells.index');
        Route::get('available-spells', [\App\Http\Controllers\Api\CharacterSpellController::class, 'available'])
            ->name('spells.available');
        Route::post('spells', [\App\Http\Controllers\Api\CharacterSpellController::class, 'store'])
            ->name('spells.store');
        Route::delete('spells/{spell}', [\App\Http\Controllers\Api\CharacterSpellController::class, 'destroy'])
            ->name('spells.destroy');
        Route::patch('spells/{spell}/prepare', [\App\Http\Controllers\Api\CharacterSpellController::class, 'prepare'])
            ->name('spells.prepare');
        Route::patch('spells/{spell}/unprepare', [\App\Http\Controllers\Api\CharacterSpellController::class, 'unprepare'])
            ->name('spells.unprepare');
        Route::get('spell-slots', [\App\Http\Controllers\Api\CharacterSpellController::class, 'slots'])
            ->name('spell-slots');

        // Character Equipment Management
        Route::get('equipment', [\App\Http\Controllers\Api\CharacterEquipmentController::class, 'index'])
            ->name('equipment.index');
        Route::post('equipment', [\App\Http\Controllers\Api\CharacterEquipmentController::class, 'store'])
            ->name('equipment.store');
        Route::patch('equipment/{equipment}', [\App\Http\Controllers\Api\CharacterEquipmentController::class, 'update'])
            ->name('equipment.update');
        Route::delete('equipment/{equipment}', [\App\Http\Controllers\Api\CharacterEquipmentController::class, 'destroy'])
            ->name('equipment.destroy');

        // Character Proficiencies
        Route::get('proficiencies', [\App\Http\Controllers\Api\CharacterProficiencyController::class, 'index'])
            ->name('proficiencies.index');
        Route::get('proficiency-choices', [\App\Http\Controllers\Api\CharacterProficiencyController::class, 'choices'])
            ->name('proficiencies.choices');
        Route::post('proficiency-choices', [\App\Http\Controllers\Api\CharacterProficiencyController::class, 'storeChoice'])
            ->name('proficiencies.storeChoice');
        Route::post('proficiencies/populate', [\App\Http\Controllers\Api\CharacterProficiencyController::class, 'populate'])
            ->name('proficiencies.populate');

        // Character Features
        Route::get('features', [\App\Http\Controllers\Api\CharacterFeatureController::class, 'index'])
            ->name('features.index');
        Route::post('features/populate', [\App\Http\Controllers\Api\CharacterFeatureController::class, 'populate'])
            ->name('features.populate');
        Route::delete('features/{source}', [\App\Http\Controllers\Api\CharacterFeatureController::class, 'clear'])
            ->name('features.clear');

        // Character Level-Up
        Route::post('level-up', \App\Http\Controllers\Api\CharacterLevelUpController::class)
            ->name('level-up');

        // ASI Choice (Feat or Ability Score Increase)
        Route::post('asi-choice', \App\Http\Controllers\Api\AsiChoiceController::class)
            ->name('asi-choice');

        // Character Classes (Multiclass Support)
        Route::get('classes', [\App\Http\Controllers\Api\CharacterClassController::class, 'index'])
            ->name('classes.index');
        Route::post('classes', [\App\Http\Controllers\Api\CharacterClassController::class, 'store'])
            ->name('classes.store');
        Route::delete('classes/{class}', [\App\Http\Controllers\Api\CharacterClassController::class, 'destroy'])
            ->name('classes.destroy');
        Route::post('classes/{class}/level-up', [\App\Http\Controllers\Api\CharacterClassController::class, 'levelUp'])
            ->name('classes.level-up');
        Route::put('classes/{class}/subclass', [\App\Http\Controllers\Api\CharacterClassController::class, 'setSubclass'])
            ->name('classes.set-subclass');

        // Character Notes (Personality traits, ideals, bonds, flaws, backstory, custom notes)
        Route::get('notes', [\App\Http\Controllers\Api\CharacterNoteController::class, 'index'])
            ->name('notes.index');
        Route::post('notes', [\App\Http\Controllers\Api\CharacterNoteController::class, 'store'])
            ->name('notes.store');
        Route::get('notes/{note}', [\App\Http\Controllers\Api\CharacterNoteController::class, 'show'])
            ->name('notes.show');
        Route::put('notes/{note}', [\App\Http\Controllers\Api\CharacterNoteController::class, 'update'])
            ->name('notes.update');
        Route::delete('notes/{note}', [\App\Http\Controllers\Api\CharacterNoteController::class, 'destroy'])
            ->name('notes.destroy');
    });
});
