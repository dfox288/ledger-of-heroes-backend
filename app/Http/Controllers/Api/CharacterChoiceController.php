<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Character\ResolveChoiceRequest;
use App\Http\Resources\ChoiceResultResource;
use App\Http\Resources\PendingChoiceResource;
use App\Http\Resources\PendingChoicesResource;
use App\Models\Character;
use App\Services\CharacterChoiceService;
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
     */
    public function index(Request $request, Character $character): PendingChoicesResource
    {
        $type = $request->query('type');
        $choices = $this->choiceService->getPendingChoices($character, $type);
        $summary = $this->choiceService->getSummary($character);

        return new PendingChoicesResource([
            'choices' => $choices,
            'summary' => $summary,
        ]);
    }

    /**
     * Get a specific pending choice.
     *
     * Returns details about a single choice including available options.
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
     */
    public function resolve(ResolveChoiceRequest $request, Character $character, string $choiceId): ChoiceResultResource
    {
        $this->choiceService->resolveChoice($character, $choiceId, $request->validated());

        return new ChoiceResultResource([
            'message' => 'Choice resolved successfully',
            'choice_id' => $choiceId,
        ]);
    }

    /**
     * Undo a resolved choice.
     *
     * Reverts a previously made choice, if the choice type supports undo.
     */
    public function undo(Character $character, string $choiceId): ChoiceResultResource
    {
        $this->choiceService->undoChoice($character, $choiceId);

        return new ChoiceResultResource([
            'message' => 'Choice undone successfully',
            'choice_id' => $choiceId,
        ]);
    }
}
