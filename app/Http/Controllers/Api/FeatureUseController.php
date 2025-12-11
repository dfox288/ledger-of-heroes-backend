<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Services\FeatureUseService;
use Illuminate\Http\JsonResponse;

class FeatureUseController extends Controller
{
    public function __construct(
        private readonly FeatureUseService $featureUseService
    ) {}

    /**
     * Get all limited-use features for a character.
     *
     * GET /api/v1/characters/{character}/feature-uses
     */
    public function index(Character $character): JsonResponse
    {
        $features = $this->featureUseService->getFeaturesWithUses($character);

        return response()->json([
            'data' => $features->values(),
        ]);
    }

    /**
     * Use a feature (decrement uses).
     *
     * POST /api/v1/characters/{character}/features/{characterFeatureId}/use
     *
     * @response array{success: bool, uses_remaining: int, max_uses: int}
     */
    public function use(Character $character, int $characterFeatureId): JsonResponse
    {
        $feature = $character->features()->find($characterFeatureId);

        if (! $feature) {
            return response()->json([
                'message' => 'Feature not found',
            ], 404);
        }

        $success = $this->featureUseService->useFeature($character, $characterFeatureId);

        if (! $success) {
            return response()->json([
                'message' => 'No uses remaining for this feature',
            ], 422);
        }

        $feature->refresh();

        return response()->json([
            'success' => true,
            'uses_remaining' => $feature->uses_remaining,
            'max_uses' => $feature->max_uses,
        ]);
    }

    /**
     * Reset a feature's uses (for manual override/DM fiat).
     *
     * POST /api/v1/characters/{character}/features/{characterFeatureId}/reset
     *
     * @response array{success: bool, uses_remaining: int, max_uses: int}
     */
    public function reset(Character $character, int $characterFeatureId): JsonResponse
    {
        $feature = $character->features()->find($characterFeatureId);

        if (! $feature) {
            return response()->json([
                'message' => 'Feature not found',
            ], 404);
        }

        $this->featureUseService->resetFeature($character, $characterFeatureId);

        $feature->refresh();

        return response()->json([
            'success' => true,
            'uses_remaining' => $feature->uses_remaining,
            'max_uses' => $feature->max_uses,
        ]);
    }
}
