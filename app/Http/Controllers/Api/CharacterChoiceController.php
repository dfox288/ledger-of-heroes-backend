<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Character\ResolveChoiceRequest;
use App\Http\Resources\PendingChoiceResource;
use App\Http\Resources\PendingChoicesResource;
use App\Models\Character;
use App\Services\CharacterChoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CharacterChoiceController extends Controller
{
    public function __construct(
        private readonly CharacterChoiceService $choiceService
    ) {}

    /**
     * List all pending choices for a character.
     *
     * Returns all choices that need user input, grouped with summary statistics.
     *
     * @queryParam type string Filter by choice type. Example: proficiency
     *
     * @response 200 scenario="Success" {
     *   "data": {
     *     "choices": [],
     *     "summary": {
     *       "total_pending": 0,
     *       "required_pending": 0,
     *       "optional_pending": 0,
     *       "by_type": {},
     *       "by_source": {}
     *     }
     *   }
     * }
     */
    public function index(Request $request, Character $character): PendingChoicesResource
    {
        $type = $request->query('type');
        $choices = $this->choiceService->getPendingChoices($character, $type);
        $summary = $this->choiceService->getSummary($character);

        return new PendingChoicesResource($choices, $summary);
    }

    /**
     * Get a specific pending choice.
     *
     * Returns details about a single choice including available options.
     *
     * @response 200 scenario="Success" {
     *   "data": {
     *     "id": "proficiency:class:1:1:skill_choice_1",
     *     "type": "proficiency",
     *     "subtype": "skill",
     *     "source": "class",
     *     "source_name": "Rogue",
     *     "level_granted": 1,
     *     "required": true,
     *     "quantity": 4,
     *     "remaining": 2,
     *     "selected": ["stealth", "sleight-of-hand"],
     *     "options": [],
     *     "options_endpoint": null,
     *     "metadata": {}
     *   }
     * }
     * @response 404 scenario="Not Found" {"message": "Choice not found", "choice_id": "invalid:id"}
     */
    public function show(Character $character, string $choiceId): PendingChoiceResource
    {
        $choice = $this->choiceService->getChoice($character, $choiceId);

        return new PendingChoiceResource($choice);
    }

    /**
     * Resolve a pending choice.
     *
     * Submit the user's selection to resolve a choice. The request format varies by choice type.
     *
     * @response 200 scenario="Success" {"message": "Choice resolved successfully", "choice_id": "proficiency:class:1:1:skill_choice_1"}
     * @response 404 scenario="Not Found" {"message": "Choice not found", "choice_id": "invalid:id"}
     * @response 422 scenario="Invalid Selection" {"message": "Invalid selection for choice", "choice_id": "...", "selection": "..."}
     */
    public function resolve(ResolveChoiceRequest $request, Character $character, string $choiceId): JsonResponse
    {
        $this->choiceService->resolveChoice($character, $choiceId, $request->validated());

        return response()->json([
            'message' => 'Choice resolved successfully',
            'choice_id' => $choiceId,
        ]);
    }

    /**
     * Undo a resolved choice.
     *
     * Reverts a previously made choice, if the choice type supports undo.
     *
     * @response 200 scenario="Success" {"message": "Choice undone successfully", "choice_id": "..."}
     * @response 404 scenario="Not Found" {"message": "Choice not found", "choice_id": "invalid:id"}
     * @response 422 scenario="Cannot Undo" {"message": "This choice cannot be undone", "choice_id": "..."}
     */
    public function undo(Character $character, string $choiceId): JsonResponse
    {
        $this->choiceService->undoChoice($character, $choiceId);

        return response()->json([
            'message' => 'Choice undone successfully',
            'choice_id' => $choiceId,
        ]);
    }
}
