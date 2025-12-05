<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CharacterFeatureResource;
use App\Models\Character;
use App\Services\CharacterFeatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CharacterFeatureController extends Controller
{
    public function __construct(
        private CharacterFeatureService $featureService
    ) {}

    /**
     * List all features for a character.
     *
     * Returns features the character has gained from class, race, background, and feats.
     * Features include class abilities (Second Wind, Action Surge), racial traits (Darkvision),
     * and background features (Military Rank).
     *
     * **Examples:**
     * ```
     * GET /api/v1/characters/1/features
     * ```
     *
     * **Response:**
     * ```json
     * {
     *   "data": [
     *     {
     *       "id": 1,
     *       "source": "class",
     *       "level_acquired": 1,
     *       "feature_type": "class_feature",
     *       "uses_remaining": null,
     *       "max_uses": null,
     *       "has_limited_uses": false,
     *       "feature": {
     *         "id": 42,
     *         "name": "Second Wind",
     *         "description": "You have a limited well of stamina...",
     *         "level": 1,
     *         "is_optional": false
     *       }
     *     },
     *     {
     *       "id": 2,
     *       "source": "race",
     *       "level_acquired": 1,
     *       "feature_type": "trait",
     *       "uses_remaining": null,
     *       "max_uses": null,
     *       "has_limited_uses": false,
     *       "feature": {
     *         "id": 15,
     *         "name": "Darkvision",
     *         "description": "You can see in dim light within 60 feet...",
     *         "category": "sense"
     *       }
     *     }
     *   ]
     * }
     * ```
     */
    public function index(Character $character): AnonymousResourceCollection
    {
        $features = $this->featureService->getCharacterFeatures($character);

        return CharacterFeatureResource::collection($features);
    }

    /**
     * Sync features from class, race, and background.
     *
     * Syncs features based on character's selections and level.
     * This is typically called when finalizing character creation or when leveling up.
     *
     * - Class features are synced up to the character's current level
     * - Optional/choice features (like Fighting Style) are NOT synced automatically
     * - Racial traits are always synced
     * - Background features are always synced
     *
     * @x-flow character-creation
     *
     * @x-flow-step 10
     *
     * **Examples:**
     * ```
     * POST /api/v1/characters/1/features/sync
     * ```
     *
     * **Response:**
     * ```json
     * {
     *   "message": "Features synced successfully",
     *   "data": [
     *     {"id": 1, "source": "class", "feature_type": "class_feature", ...},
     *     {"id": 2, "source": "race", "feature_type": "trait", ...},
     *     {"id": 3, "source": "background", "feature_type": "trait", ...}
     *   ]
     * }
     * ```
     *
     * **Note:** This endpoint is idempotent - calling it multiple times will not create duplicates.
     * Features are synced when class/race/background changes via the PopulateCharacterAbilities listener.
     */
    public function sync(Character $character): JsonResponse
    {
        $character->load(['characterClasses.characterClass', 'race', 'background']);

        $this->featureService->populateAll($character);

        return response()->json([
            'message' => 'Features synced successfully',
            'data' => CharacterFeatureResource::collection(
                $this->featureService->getCharacterFeatures($character)
            ),
        ]);
    }

    /**
     * Clear all features from a specific source.
     *
     * Removes all features for a character from the specified source (class, race, background).
     * Useful when a character changes class, race, or background.
     *
     * **Valid sources:** `class`, `race`, `background`, `feat`, `item`
     *
     * **Examples:**
     * ```
     * DELETE /api/v1/characters/1/features/class
     * DELETE /api/v1/characters/1/features/race
     * DELETE /api/v1/characters/1/features/background
     * ```
     *
     * **Response:**
     * ```json
     * {"message": "Features from class cleared successfully"}
     * ```
     *
     * **Error Response (422):**
     * ```json
     * {"message": "Invalid source. Valid sources: class, race, background, feat, item"}
     * ```
     *
     * **Note:** This is automatically called by the PopulateCharacterAbilities listener
     * when a character's class/race/background changes.
     */
    public function clear(Character $character, string $source): JsonResponse
    {
        $validSources = ['class', 'race', 'background', 'feat', 'item'];

        if (! in_array($source, $validSources)) {
            return response()->json([
                'message' => 'Invalid source. Valid sources: '.implode(', ', $validSources),
            ], 422);
        }

        $this->featureService->clearFeatures($character, $source);

        return response()->json([
            'message' => "Features from {$source} cleared successfully",
        ]);
    }
}
