<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CharacterLanguageResource;
use App\Http\Resources\LanguageChoicesResource;
use App\Http\Resources\SyncResultResource;
use App\Models\Character;
use App\Services\CharacterLanguageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
     * Get pending language choices for a character.
     *
     * Returns language choices that need user input (e.g., "choose 1 language").
     * Organized by source (race, background, feat).
     *
     * @x-flow character-creation
     *
     * @x-flow-step 6
     *
     * **Examples:**
     * ```
     * GET /api/v1/characters/1/language-choices
     * ```
     *
     * **Response:**
     * ```json
     * {
     *   "data": {
     *     "race": {
     *       "known": [
     *         {"id": 1, "name": "Common", "slug": "common", "script": "Common"}
     *       ],
     *       "choices": {
     *         "quantity": 1,
     *         "remaining": 1,
     *         "selected": [],
     *         "options": [
     *           {"id": 2, "name": "Dwarvish", "slug": "dwarvish", "script": "Dwarvish"},
     *           {"id": 3, "name": "Elvish", "slug": "elvish", "script": "Elvish"}
     *         ]
     *       }
     *     },
     *     "background": {
     *       "known": [],
     *       "choices": {
     *         "quantity": 2,
     *         "remaining": 2,
     *         "selected": [],
     *         "options": [...]
     *       }
     *     },
     *     "feat": {
     *       "known": [],
     *       "choices": {
     *         "quantity": 0,
     *         "remaining": 0,
     *         "selected": [],
     *         "options": []
     *       }
     *     }
     *   }
     * }
     * ```
     *
     * **Fields:**
     * - `known`: Languages already known from this source
     * - `choices.quantity`: Total number of choices required from this source
     * - `choices.remaining`: Number of choices still needed
     * - `choices.selected`: Array of language IDs already chosen for this source
     * - `choices.options`: Available languages to choose from (excludes already known)
     *
     * @response LanguageChoicesResource
     */
    public function choices(Character $character): LanguageChoicesResource
    {
        $character->load(['race', 'background', 'features', 'languages']);

        $choices = $this->languageService->getPendingChoices($character);

        return new LanguageChoicesResource($choices);
    }

    /**
     * Make a language choice.
     *
     * Submits the user's selection for a language choice.
     *
     * @x-flow character-creation
     *
     * @x-flow-step 7
     *
     * **Request Body:**
     * ```json
     * {
     *   "source": "race",
     *   "language_ids": [3]
     * }
     * ```
     *
     * **Parameters:**
     * - `source` (required): One of `race`, `background`, `feat`
     * - `language_ids` (required): Array of language IDs matching the required quantity
     *
     * **Response:**
     * ```json
     * {
     *   "message": "Languages saved successfully",
     *   "data": [
     *     {"id": 1, "source": "race", "language": {"id": 1, "name": "Common", ...}},
     *     {"id": 2, "source": "race", "language": {"id": 3, "name": "Elvish", ...}}
     *   ]
     * }
     * ```
     *
     * **Error Responses (422):**
     * - "Must choose exactly N languages, got M" - Wrong quantity selected
     * - "Language ID X is already known" - Duplicate language selected
     * - "No language choices available for source" - Source has no choices
     */
    public function storeChoice(Request $request, Character $character): SyncResultResource|JsonResponse
    {
        $validated = $request->validate([
            'source' => ['required', 'in:race,background,feat'],
            'language_ids' => ['required', 'array', 'min:1'],
            'language_ids.*' => ['required', 'integer', 'exists:languages,id'],
        ]);

        $character->load(['race', 'background', 'features', 'languages']);

        try {
            $this->languageService->makeChoice(
                $character,
                $validated['source'],
                $validated['language_ids']
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return SyncResultResource::withMessage(
            'Languages saved successfully',
            CharacterLanguageResource::collection(
                $this->languageService->getCharacterLanguages($character)
            )
        );
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
