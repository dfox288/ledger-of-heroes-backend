<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Services\LevelUpService;
use Illuminate\Http\JsonResponse;

class CharacterLevelUpController extends Controller
{
    public function __construct(
        private LevelUpService $levelUpService
    ) {}

    /**
     * Level up a character.
     *
     * Increases character level by 1, grants HP, class features, and updates spell slots.
     *
     * @group Character Builder
     *
     * @urlParam character integer required The character ID. Example: 1
     *
     * @response 200 {
     *   "previous_level": 3,
     *   "new_level": 4,
     *   "hp_increase": 7,
     *   "new_max_hp": 35,
     *   "features_gained": [
     *     {"id": 1, "name": "Ability Score Improvement", "description": "..."}
     *   ],
     *   "spell_slots": {"1": 4, "2": 3},
     *   "asi_pending": true
     * }
     * @response 404 {"message": "Character not found"}
     * @response 422 {"message": "Character is already at maximum level (20)."}
     * @response 422 {"message": "Character must be complete before leveling up."}
     */
    public function __invoke(Character $character): JsonResponse
    {
        $result = $this->levelUpService->levelUp($character);

        return response()->json($result->toArray());
    }
}
