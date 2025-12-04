<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CharacterProficiencyResource;
use App\Http\Resources\ChoicesResource;
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
     * Includes both skill proficiencies and equipment proficiencies (armor, weapons, tools).
     *
     * **Examples:**
     * ```
     * GET /api/v1/characters/1/proficiencies
     * ```
     *
     * **Response:**
     * ```json
     * {
     *   "data": [
     *     {
     *       "id": 1,
     *       "source": "class",
     *       "expertise": false,
     *       "skill": {
     *         "id": 3,
     *         "name": "Athletics",
     *         "slug": "athletics",
     *         "ability_code": "STR"
     *       }
     *     },
     *     {
     *       "id": 2,
     *       "source": "class",
     *       "expertise": false,
     *       "proficiency_type": {
     *         "id": 5,
     *         "name": "Light Armor",
     *         "slug": "light-armor",
     *         "category": "armor"
     *       }
     *     }
     *   ]
     * }
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
     * Organized by source (class, race, background) and choice group.
     *
     * **Examples:**
     * ```
     * GET /api/v1/characters/1/proficiency-choices
     * ```
     *
     * **Response:**
     * ```json
     * {
     *   "data": {
     *     "class": {
     *       "skill_choice_1": {
     *         "quantity": 2,
     *         "remaining": 0,
     *         "selected_skills": [1, 3],
     *         "selected_proficiency_types": [],
     *         "options": [
     *           {"type": "skill", "skill_id": 1, "skill": {"id": 1, "name": "Acrobatics", "slug": "acrobatics"}},
     *           {"type": "skill", "skill_id": 3, "skill": {"id": 3, "name": "Athletics", "slug": "athletics"}},
     *           {"type": "skill", "skill_id": 10, "skill": {"id": 10, "name": "Intimidation", "slug": "intimidation"}}
     *         ]
     *       }
     *     },
     *     "race": {},
     *     "background": {}
     *   }
     * }
     * ```
     *
     * **Fields:**
     * - `quantity`: Total number of choices required
     * - `remaining`: Number of choices still needed (quantity - already chosen)
     * - `selected_skills`: Array of skill IDs already chosen
     * - `selected_proficiency_types`: Array of proficiency type IDs already chosen
     * - `options`: All available options for this choice group (always includes full list)
     */
    public function choices(Character $character): ChoicesResource
    {
        $character->load(['characterClasses.characterClass', 'race', 'background']);

        $choices = $this->proficiencyService->getPendingChoices($character);

        return new ChoicesResource($choices);
    }

    /**
     * Make a proficiency choice.
     *
     * Submits the user's selection for a proficiency choice group.
     *
     * **Request Body:**
     * ```json
     * {
     *   "source": "class",
     *   "choice_group": "skill_choice_1",
     *   "skill_ids": [1, 5]
     * }
     * ```
     *
     * **Parameters:**
     * - `source` (required): One of `class`, `race`, `background`
     * - `choice_group` (required): The choice group name from the choices endpoint
     * - `skill_ids` (required): Array of skill IDs matching the required quantity
     *
     * **Response:**
     * ```json
     * {
     *   "message": "Choice saved successfully",
     *   "data": [
     *     {"id": 1, "source": "class", "skill": {"id": 1, "name": "Acrobatics", ...}},
     *     {"id": 2, "source": "class", "skill": {"id": 5, "name": "History", ...}}
     *   ]
     * }
     * ```
     *
     * **Error Responses (422):**
     * - "Must choose exactly N skills, got M" - Wrong quantity selected
     * - "Skill ID X is not a valid option for this choice" - Invalid skill selected
     * - "No choice group 'X' found for source" - Invalid choice group
     */
    public function storeChoice(Request $request, Character $character): JsonResponse
    {
        $validated = $request->validate([
            'source' => ['required', 'in:class,race,background'],
            'choice_group' => ['required', 'string'],
            'skill_ids' => ['required', 'array', 'min:1'],
            'skill_ids.*' => ['required', 'integer', 'exists:skills,id'],
        ]);

        $character->load(['characterClasses.characterClass', 'race', 'background']);

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
     * - Fixed proficiencies (armor, weapons, saving throws) are auto-populated
     * - Choice-based proficiencies (skills) require using the proficiency-choices endpoint
     *
     * **Examples:**
     * ```
     * POST /api/v1/characters/1/proficiencies/populate
     * ```
     *
     * **Response:**
     * ```json
     * {
     *   "message": "Proficiencies populated successfully",
     *   "data": [
     *     {"id": 1, "source": "class", "proficiency_type": {"name": "Light Armor", ...}},
     *     {"id": 2, "source": "class", "proficiency_type": {"name": "Heavy Armor", ...}},
     *     {"id": 3, "source": "class", "proficiency_type": {"name": "Simple Weapons", ...}}
     *   ]
     * }
     * ```
     *
     * **Note:** This endpoint is idempotent - calling it multiple times will not create duplicates.
     * Proficiencies are auto-populated when class/race/background changes via the PopulateCharacterAbilities listener.
     */
    public function populate(Character $character): JsonResponse
    {
        $character->load(['characterClasses.characterClass', 'race', 'background']);

        $this->proficiencyService->populateAll($character);

        return response()->json([
            'message' => 'Proficiencies populated successfully',
            'data' => CharacterProficiencyResource::collection(
                $this->proficiencyService->getCharacterProficiencies($character)
            ),
        ]);
    }
}
