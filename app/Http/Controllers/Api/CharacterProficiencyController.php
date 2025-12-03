<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CharacterProficiencyResource;
use App\Models\Character;
use App\Services\CharacterProficiencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CharacterProficiencyController extends Controller
{
    public function __construct(
        private CharacterProficiencyService $proficiencyService
    ) {}

    /**
     * List all proficiencies for a character.
     *
     * Returns proficiencies the character has gained from class, race, background, and feats.
     *
     * **Examples:**
     * ```
     * GET /api/v1/characters/1/proficiencies
     * ```
     */
    public function index(Character $character): AnonymousResourceCollection
    {
        $proficiencies = $this->proficiencyService->getCharacterProficiencies($character);

        return CharacterProficiencyResource::collection($proficiencies);
    }

    /**
     * Get pending proficiency choices for a character.
     *
     * Returns proficiency choices that need user input (e.g., "pick 2 skills from this list").
     *
     * **Examples:**
     * ```
     * GET /api/v1/characters/1/proficiency-choices
     * ```
     */
    public function choices(Character $character): JsonResponse
    {
        $character->load(['characterClass', 'race', 'background']);

        $choices = $this->proficiencyService->getPendingChoices($character);

        return response()->json(['data' => $choices]);
    }

    /**
     * Make a proficiency choice.
     *
     * Submits the user's selection for a proficiency choice group.
     *
     * **Examples:**
     * ```
     * POST /api/v1/characters/1/proficiency-choices
     * {
     *   "source": "class",
     *   "choice_group": "skill_choice_1",
     *   "skill_ids": [1, 5]
     * }
     * ```
     */
    public function storeChoice(Request $request, Character $character): JsonResponse
    {
        $validated = $request->validate([
            'source' => ['required', 'in:class,race,background'],
            'choice_group' => ['required', 'string'],
            'skill_ids' => ['required', 'array', 'min:1'],
            'skill_ids.*' => ['required', 'integer', 'exists:skills,id'],
        ]);

        $character->load(['characterClass', 'race', 'background']);

        try {
            $this->proficiencyService->makeSkillChoice(
                $character,
                $validated['source'],
                $validated['choice_group'],
                $validated['skill_ids']
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Choice saved successfully',
            'data' => CharacterProficiencyResource::collection(
                $this->proficiencyService->getCharacterProficiencies($character)
            ),
        ]);
    }

    /**
     * Populate proficiencies from class, race, and background.
     *
     * Auto-populates fixed proficiencies based on character's selections.
     * This is typically called when finalizing character creation.
     *
     * **Examples:**
     * ```
     * POST /api/v1/characters/1/proficiencies/populate
     * ```
     */
    public function populate(Character $character): JsonResponse
    {
        $character->load(['characterClass', 'race', 'background']);

        $this->proficiencyService->populateAll($character);

        return response()->json([
            'message' => 'Proficiencies populated successfully',
            'data' => CharacterProficiencyResource::collection(
                $this->proficiencyService->getCharacterProficiencies($character)
            ),
        ]);
    }
}
