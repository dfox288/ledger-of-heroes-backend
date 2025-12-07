<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CharacterProficiencyResource;
use App\Http\Resources\SyncResultResource;
use App\Models\Character;
use App\Services\CharacterProficiencyService;
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
     *
     * @response AnonymousResourceCollection<CharacterProficiencyResource>
     */
    public function index(Character $character): AnonymousResourceCollection
    {
        $proficiencies = $this->proficiencyService->getCharacterProficiencies($character);

        return CharacterProficiencyResource::collection($proficiencies);
    }

    /**
     * Sync proficiencies from class, race, and background.
     *
     * Syncs fixed proficiencies based on character's selections.
     * This is typically called when finalizing character creation.
     *
     * - Fixed proficiencies (armor, weapons, saving throws) are synced automatically
     * - Choice-based proficiencies (skills) require using the proficiency-choices endpoint
     *
     * @x-flow character-creation
     *
     * @x-flow-step 5
     *
     * **Examples:**
     * ```
     * POST /api/v1/characters/1/proficiencies/sync
     * ```
     *
     * **Response:**
     * ```json
     * {
     *   "message": "Proficiencies synced successfully",
     *   "data": [
     *     {"id": 1, "source": "class", "proficiency_type": {"name": "Light Armor", ...}},
     *     {"id": 2, "source": "class", "proficiency_type": {"name": "Heavy Armor", ...}},
     *     {"id": 3, "source": "class", "proficiency_type": {"name": "Simple Weapons", ...}}
     *   ]
     * }
     * ```
     *
     * **Note:** This endpoint is idempotent - calling it multiple times will not create duplicates.
     * Proficiencies are synced when class/race/background changes via the PopulateCharacterAbilities listener.
     */
    public function sync(Character $character): SyncResultResource
    {
        $character->load(['characterClasses.characterClass', 'race', 'background']);

        $this->proficiencyService->populateAll($character);

        return SyncResultResource::withMessage(
            'Proficiencies synced successfully',
            CharacterProficiencyResource::collection(
                $this->proficiencyService->getCharacterProficiencies($character)
            )
        );
    }
}
