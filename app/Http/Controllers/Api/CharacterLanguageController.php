<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CharacterLanguageResource;
use App\Http\Resources\SyncResultResource;
use App\Models\Character;
use App\Services\CharacterLanguageService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CharacterLanguageController extends Controller
{
    public function __construct(
        private CharacterLanguageService $languageService
    ) {}

    /**
     * List all languages for a character.
     *
     * Returns languages the character knows from race, background, and feats.
     *
     * **Examples:**
     * ```
     * GET /api/v1/characters/1/languages
     * ```
     *
     * **Response:**
     * ```json
     * {
     *   "data": [
     *     {
     *       "id": 1,
     *       "source": "race",
     *       "language": {
     *         "id": 1,
     *         "name": "Common",
     *         "slug": "common",
     *         "script": "Common"
     *       }
     *     },
     *     {
     *       "id": 2,
     *       "source": "race",
     *       "language": {
     *         "id": 5,
     *         "name": "Elvish",
     *         "slug": "elvish",
     *         "script": "Elvish"
     *       }
     *     }
     *   ]
     * }
     * ```
     *
     * @response AnonymousResourceCollection<CharacterLanguageResource>
     */
    public function index(Character $character): AnonymousResourceCollection
    {
        $languages = $this->languageService->getCharacterLanguages($character);

        return CharacterLanguageResource::collection($languages);
    }

    /**
     * Sync languages from race, background, and feats.
     *
     * Syncs fixed languages based on character's selections.
     * This is typically called when finalizing character creation.
     *
     * - Fixed languages are synced automatically
     * - Choice-based languages require using the language-choices endpoint
     *
     * @x-flow character-creation
     *
     * @x-flow-step 7
     *
     * **Examples:**
     * ```
     * POST /api/v1/characters/1/languages/sync
     * ```
     *
     * **Response:**
     * ```json
     * {
     *   "message": "Languages synced successfully",
     *   "data": [
     *     {"id": 1, "source": "race", "language": {"name": "Common", ...}},
     *     {"id": 2, "source": "race", "language": {"name": "Elvish", ...}}
     *   ]
     * }
     * ```
     *
     * **Note:** This endpoint is idempotent - calling it multiple times will not create duplicates.
     */
    public function sync(Character $character): SyncResultResource
    {
        $character->load(['race', 'background', 'features']);

        $this->languageService->populateFixed($character);

        return SyncResultResource::withMessage(
            'Languages synced successfully',
            CharacterLanguageResource::collection(
                $this->languageService->getCharacterLanguages($character)
            )
        );
    }
}
