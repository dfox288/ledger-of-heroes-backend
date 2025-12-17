<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Character\UpdateCounterRequest;
use App\Http\Resources\CounterResource;
use App\Models\Character;
use App\Models\CharacterCounter;
use App\Services\CounterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CounterController extends Controller
{
    public function __construct(
        private readonly CounterService $counterService
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
        $counters = $this->counterService->getCountersForCharacter($character);

        return CounterResource::collection($counters);
    }

    /**
     * Update a counter's current value.
     *
     * PATCH /api/v1/characters/{character}/counters/{id}
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
        int $id
    ): JsonResponse|CounterResource {
        // Find the counter by ID
        $counter = CharacterCounter::where('id', $id)
            ->where('character_id', $character->id)
            ->first();

        if (! $counter) {
            return response()->json([
                'message' => 'Counter not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        // Handle action mode
        if ($request->has('action')) {
            return $this->handleAction($request->action, $counter);
        }

        // Handle spent mode
        $spent = $request->spent;
        $max = $counter->max_uses;

        // Unlimited counters (-1) cannot be spent
        if ($counter->isUnlimited()) {
            return response()->json([
                'message' => 'Cannot set spent value for unlimited counter.',
                'errors' => ['spent' => ['Unlimited counters cannot have a spent value.']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Validate spent doesn't exceed max
        if ($spent > $max) {
            return response()->json([
                'message' => 'The spent value exceeds the maximum.',
                'errors' => ['spent' => ['The spent value cannot exceed the maximum of '.$max.'.']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Calculate remaining uses (max - spent)
        // current_uses = remaining uses (null = full)
        $remaining = $max - $spent;
        $counter->update(['current_uses' => $remaining < $max ? $remaining : null]);

        return $this->getUpdatedCounter($counter);
    }

    /**
     * Handle action-based counter updates.
     */
    private function handleAction(string $action, CharacterCounter $counter): JsonResponse|CounterResource
    {
        switch ($action) {
            case 'use':
                if (! $counter->use()) {
                    return response()->json([
                        'message' => 'No uses remaining for this counter.',
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
                break;

            case 'restore':
                if ($counter->isUnlimited()) {
                    return response()->json([
                        'message' => 'Cannot restore unlimited counter.',
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
                $remaining = $counter->remaining;
                if ($remaining >= $counter->max_uses) {
                    return response()->json([
                        'message' => 'Counter is already at maximum.',
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
                // Increment by setting current_uses
                $newRemaining = $remaining + 1;
                $counter->update(['current_uses' => $newRemaining < $counter->max_uses ? $newRemaining : null]);
                break;

            case 'reset':
                $counter->reset();
                break;
        }

        return $this->getUpdatedCounter($counter);
    }

    /**
     * Get updated counter after modification.
     */
    private function getUpdatedCounter(CharacterCounter $counter): CounterResource
    {
        $counter->refresh();

        // Format for resource
        $data = [
            'id' => $counter->id,
            'name' => $counter->counter_name,
            'current' => $counter->remaining,
            'max' => $counter->max_uses,
            'reset_on' => $this->formatResetTiming($counter->reset_timing),
            'source_type' => $counter->source_type,
            'source_slug' => $counter->source_slug,
            'unlimited' => $counter->isUnlimited(),
        ];

        return new CounterResource($data);
    }

    /**
     * Format reset timing code to human-readable label.
     */
    private function formatResetTiming(?string $timing): ?string
    {
        return match ($timing) {
            'S' => 'short_rest',
            'L' => 'long_rest',
            'D' => 'dawn',
            default => null,
        };
    }
}
