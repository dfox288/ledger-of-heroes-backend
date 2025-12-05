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
use App\Http\Controllers\Api\MediaController;
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
    | Character Builder API - Flow Documentation
    |--------------------------------------------------------------------------
    |
    | CHARACTER CREATION FLOW (Wizard):
    | 1. POST /characters                      - Create character shell
    | 2. PATCH /characters/{id}                - Set race, background, ability scores
    | 3. POST /characters/{id}/classes         - Add primary class
    | 4. GET /characters/{id}/proficiency-choices
    | 5. POST /characters/{id}/proficiency-choices  - Make proficiency selections
    | 6. GET /characters/{id}/language-choices
    | 7. POST /characters/{id}/language-choices     - Make language selections
    | 8. GET /characters/{id}/available-spells?max_level=1
    | 9. POST /characters/{id}/spells          - Learn starting spells
    | 10. POST /characters/{id}/features/sync       - Apply features from sources
    | 11. GET /characters/{id}                 - Verify creation complete
    |
    | GAMEPLAY FLOW:
    |
    | Combat:
    | - POST /characters/{id}/conditions       - Apply condition
    | - DELETE /characters/{id}/conditions/{slug}  - Remove condition
    | - POST /characters/{id}/spell-slots/use  - Cast spell (use slot)
    | - POST /characters/{id}/death-saves      - Death saving throw
    | - POST /characters/{id}/death-saves/stabilize - Stabilize dying character
    | - DELETE /characters/{id}/death-saves    - Reset death saves
    |
    | Rest:
    | - POST /characters/{id}/short-rest       - Short rest
    | - POST /characters/{id}/hit-dice/spend   - Heal during short rest
    | - POST /characters/{id}/long-rest        - Long rest (full reset)
    |
    | Level Up:
    | - POST /characters/{id}/classes/{class}/level-up  - Gain level in class
    | - PUT /characters/{id}/classes/{class}/subclass   - Choose subclass (level 3)
    | - POST /characters/{id}/asi-choice       - ASI or feat selection
    | - GET /characters/{id}/feature-selection-choices  - Check new choices
    | - POST /characters/{id}/feature-selections        - Select invocations, etc.
    |
    */
    Route::apiResource('characters', CharacterController::class);
    Route::get('characters/{character}/stats', [CharacterController::class, 'stats'])
        ->name('characters.stats');
    Route::get('characters/{character}/summary', [CharacterController::class, 'summary'])
        ->name('characters.summary');

    // Character Spell Management
    Route::prefix('characters/{character}')->name('characters.')->group(function () {
        Route::get('spells', [\App\Http\Controllers\Api\CharacterSpellController::class, 'index'])
            ->name('spells.index');
        Route::get('available-spells', [\App\Http\Controllers\Api\CharacterSpellController::class, 'available'])
            ->name('spells.available');
        Route::post('spells', [\App\Http\Controllers\Api\CharacterSpellController::class, 'store'])
            ->name('spells.store');
        Route::delete('spells/{spellIdOrSlug}', [\App\Http\Controllers\Api\CharacterSpellController::class, 'destroy'])
            ->name('spells.destroy');
        Route::patch('spells/{spellIdOrSlug}/prepare', [\App\Http\Controllers\Api\CharacterSpellController::class, 'prepare'])
            ->name('spells.prepare');
        Route::patch('spells/{spellIdOrSlug}/unprepare', [\App\Http\Controllers\Api\CharacterSpellController::class, 'unprepare'])
            ->name('spells.unprepare');
        Route::get('spell-slots', [\App\Http\Controllers\Api\CharacterSpellController::class, 'slots'])
            ->name('spell-slots');
        Route::get('spell-slots/tracked', [\App\Http\Controllers\Api\SpellSlotController::class, 'index'])
            ->name('spell-slots.index');
        Route::post('spell-slots/use', [\App\Http\Controllers\Api\SpellSlotController::class, 'use'])
            ->name('spell-slots.use');

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
        Route::post('proficiencies/sync', [\App\Http\Controllers\Api\CharacterProficiencyController::class, 'sync'])
            ->name('proficiencies.sync');

        // Character Languages
        Route::get('languages', [\App\Http\Controllers\Api\CharacterLanguageController::class, 'index'])
            ->name('languages.index');
        Route::get('language-choices', [\App\Http\Controllers\Api\CharacterLanguageController::class, 'choices'])
            ->name('languages.choices');
        Route::post('language-choices', [\App\Http\Controllers\Api\CharacterLanguageController::class, 'storeChoice'])
            ->name('languages.storeChoice');
        Route::post('languages/sync', [\App\Http\Controllers\Api\CharacterLanguageController::class, 'sync'])
            ->name('languages.sync');

        // Character Features
        Route::get('features', [\App\Http\Controllers\Api\CharacterFeatureController::class, 'index'])
            ->name('features.index');
        Route::post('features/sync', [\App\Http\Controllers\Api\CharacterFeatureController::class, 'sync'])
            ->name('features.sync');
        Route::delete('features/{source}', [\App\Http\Controllers\Api\CharacterFeatureController::class, 'clear'])
            ->name('features.clear');

        // Character Level-Up
        // @deprecated - Use /classes/{class}/level-up instead
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
        Route::delete('classes/{classIdOrSlug}', [\App\Http\Controllers\Api\CharacterClassController::class, 'destroy'])
            ->name('classes.destroy');
        Route::put('classes/{classIdOrSlug}', [\App\Http\Controllers\Api\CharacterClassController::class, 'replace'])
            ->name('classes.replace');
        Route::post('classes/{classIdOrSlug}/level-up', [\App\Http\Controllers\Api\CharacterClassController::class, 'levelUp'])
            ->name('classes.level-up');
        Route::put('classes/{classIdOrSlug}/subclass', [\App\Http\Controllers\Api\CharacterClassController::class, 'setSubclass'])
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

        // Death Saves
        Route::post('death-saves', [\App\Http\Controllers\Api\CharacterDeathSaveController::class, 'store'])
            ->name('death-saves.store');
        Route::post('death-saves/stabilize', [\App\Http\Controllers\Api\CharacterDeathSaveController::class, 'stabilize'])
            ->name('death-saves.stabilize');
        Route::delete('death-saves', [\App\Http\Controllers\Api\CharacterDeathSaveController::class, 'reset'])
            ->name('death-saves.reset');

        // Hit Dice
        Route::get('hit-dice', [\App\Http\Controllers\Api\HitDiceController::class, 'index'])
            ->name('hit-dice.index');
        Route::post('hit-dice/spend', [\App\Http\Controllers\Api\HitDiceController::class, 'spend'])
            ->name('hit-dice.spend');
        Route::post('hit-dice/recover', [\App\Http\Controllers\Api\HitDiceController::class, 'recover'])
            ->name('hit-dice.recover');

        // Rest Mechanics
        Route::post('short-rest', [\App\Http\Controllers\Api\RestController::class, 'shortRest'])
            ->name('short-rest');
        Route::post('long-rest', [\App\Http\Controllers\Api\RestController::class, 'longRest'])
            ->name('long-rest');

        // Feature Selections (Invocations, Maneuvers, Metamagic, etc.)
        Route::get('feature-selections', [\App\Http\Controllers\Api\FeatureSelectionController::class, 'index'])
            ->name('feature-selections.index');
        Route::get('available-feature-selections', [\App\Http\Controllers\Api\FeatureSelectionController::class, 'available'])
            ->name('feature-selections.available');
        Route::get('feature-selection-choices', [\App\Http\Controllers\Api\FeatureSelectionController::class, 'choices'])
            ->name('feature-selections.choices');
        Route::post('feature-selections', [\App\Http\Controllers\Api\FeatureSelectionController::class, 'store'])
            ->name('feature-selections.store');
        Route::delete('feature-selections/{featureIdOrSlug}', [\App\Http\Controllers\Api\FeatureSelectionController::class, 'destroy'])
            ->name('feature-selections.destroy');

        // Conditions
        Route::get('conditions', [\App\Http\Controllers\Api\CharacterConditionController::class, 'index'])
            ->name('conditions.index');
        Route::post('conditions', [\App\Http\Controllers\Api\CharacterConditionController::class, 'store'])
            ->name('conditions.store');
        Route::delete('conditions/{conditionIdOrSlug}', [\App\Http\Controllers\Api\CharacterConditionController::class, 'destroy'])
            ->name('conditions.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Polymorphic Media Routes
    |--------------------------------------------------------------------------
    |
    | Generic media upload/delete endpoints for any model registered in
    | config/media.php. Used for character portraits, tokens, and future
    | entity media attachments.
    |
    */
    Route::prefix('{modelType}/{modelId}/media')->group(function () {
        Route::get('{collection}', [MediaController::class, 'index'])
            ->name('media.index');
        Route::post('{collection}', [MediaController::class, 'store'])
            ->name('media.store');
        Route::delete('{collection}', [MediaController::class, 'destroy'])
            ->name('media.destroy');
        Route::delete('{collection}/{mediaId}', [MediaController::class, 'destroy'])
            ->name('media.destroyOne');
    });
});
