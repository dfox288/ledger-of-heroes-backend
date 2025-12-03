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
     */
    public function index(Character $character): AnonymousResourceCollection
    {
        $features = $this->featureService->getCharacterFeatures($character);

        return CharacterFeatureResource::collection($features);
    }

    /**
     * Populate features from class, race, and background.
     *
     * Auto-populates features based on character's selections and level.
     * This is typically called when finalizing character creation or when leveling up.
     *
     * - Class features are populated up to the character's current level
     * - Optional/choice features (like Fighting Style) are NOT auto-populated
     * - Racial traits are always populated
     * - Background features are always populated
     *
     * **Examples:**
     * ```
     * POST /api/v1/characters/1/features/populate
     * ```
     */
    public function populate(Character $character): JsonResponse
    {
        $character->load(['characterClass', 'race', 'background']);

        $this->featureService->populateAll($character);

        return response()->json([
            'message' => 'Features populated successfully',
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
     * **Examples:**
     * ```
     * DELETE /api/v1/characters/1/features/class
     * DELETE /api/v1/characters/1/features/race
     * ```
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
