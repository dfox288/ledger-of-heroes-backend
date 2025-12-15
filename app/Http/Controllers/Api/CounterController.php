<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Character\UpdateCounterRequest;
use App\Http\Resources\CounterResource;
use App\Models\Character;
use App\Services\FeatureUseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CounterController extends Controller
{
    public function __construct(
        private readonly FeatureUseService $featureUseService
    ) {}

    /**
     * List all counters for a character.
     *
     * GET /api/v1/characters/{character}/counters
     *
     * @operationId counters.index
     *
     * @tags Characters, Counters
     */
    public function index(Character $character): AnonymousResourceCollection
    {
        $counters = $this->featureUseService->getCountersForCharacter($character);

        return CounterResource::collection($counters);
    }

    /**
     * Update a counter's current value.
     *
     * PATCH /api/v1/characters/{character}/counters/{slug}
     *
     * Supports two modes:
     * - Absolute: { "spent": 2 } - sets spent count directly
     * - Action: { "action": "use|restore|reset" } - incremental changes
     *
     * @operationId counters.update
     *
     * @tags Characters, Counters
     */
    public function update(
        UpdateCounterRequest $request,
        Character $character,
        string $slug
    ): JsonResponse|CounterResource {
        // Find the counter by slug
        $counters = $this->featureUseService->getCountersForCharacter($character);
        $counter = $counters->firstWhere('slug', $slug);

        if (! $counter) {
            return response()->json([
                'message' => 'Counter not found.',
            ], 404);
        }

        // Get the character feature
        $characterFeature = $character->features()->find($counter['id']);

        if (! $characterFeature) {
            return response()->json([
                'message' => 'Counter not found.',
            ], 404);
        }

        // Handle action mode
        if ($request->has('action')) {
            return $this->handleAction($request->action, $characterFeature, $counter);
        }

        // Handle spent mode
        $spent = $request->spent;
        $max = $counter['max'];

        // Validate spent doesn't exceed max
        if ($spent > $max) {
            return response()->json([
                'message' => 'The spent value exceeds the maximum.',
                'errors' => ['spent' => ['The spent value cannot exceed the maximum of '.$max.'.']],
            ], 422);
        }

        // Calculate remaining uses (max - spent)
        $remaining = $max - $spent;

        $characterFeature->update(['uses_remaining' => $remaining]);

        // Refresh and return
        return $this->getUpdatedCounter($character, $slug);
    }

    /**
     * Handle action-based counter updates.
     */
    private function handleAction(string $action, $characterFeature, array $counter): JsonResponse|CounterResource
    {
        $max = $counter['max'];
        $current = $characterFeature->uses_remaining;

        switch ($action) {
            case 'use':
                if ($current <= 0) {
                    return response()->json([
                        'message' => 'No uses remaining for this counter.',
                    ], 422);
                }
                $characterFeature->decrement('uses_remaining');
                break;

            case 'restore':
                if ($current >= $max) {
                    return response()->json([
                        'message' => 'Counter is already at maximum.',
                    ], 422);
                }
                $characterFeature->increment('uses_remaining');
                break;

            case 'reset':
                $characterFeature->update(['uses_remaining' => $max]);
                break;
        }

        // Get character from feature
        $character = $characterFeature->character;

        return $this->getUpdatedCounter($character, $counter['slug']);
    }

    /**
     * Get updated counter after modification.
     */
    private function getUpdatedCounter(Character $character, string $slug): CounterResource
    {
        $counters = $this->featureUseService->getCountersForCharacter($character);
        $counter = $counters->firstWhere('slug', $slug);

        return new CounterResource($counter);
    }
}
