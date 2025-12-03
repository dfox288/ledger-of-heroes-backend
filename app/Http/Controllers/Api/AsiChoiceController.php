<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Character\AsiChoiceRequest;
use App\Models\Character;
use App\Models\Feat;
use App\Services\AsiChoiceService;
use Illuminate\Http\JsonResponse;

class AsiChoiceController extends Controller
{
    public function __construct(
        private AsiChoiceService $asiChoiceService
    ) {}

    /**
     * Apply an ASI choice (feat or ability score increase).
     *
     * Spend one of the character's pending ASI choices to either:
     * - Take a feat (with prerequisite validation)
     * - Increase ability scores (+2 to one or +1 to two, max 20)
     *
     * @group Character Builder
     *
     * @urlParam character integer required The character ID. Example: 1
     *
     * @bodyParam choice_type string required Either "feat" or "ability_increase". Example: feat
     * @bodyParam feat_id integer Feat ID (required if choice_type is feat). Example: 42
     * @bodyParam ability_increases object Ability increases (required if choice_type is ability_increase). Example: {"STR": 2}
     *
     * @response 200 {
     *   "success": true,
     *   "choice_type": "feat",
     *   "asi_choices_remaining": 0,
     *   "changes": {
     *     "feat": {"id": 42, "name": "Alert", "slug": "alert"},
     *     "ability_increases": {},
     *     "proficiencies_gained": [],
     *     "spells_gained": []
     *   },
     *   "new_ability_scores": {
     *     "STR": 16, "DEX": 14, "CON": 14, "INT": 10, "WIS": 12, "CHA": 8
     *   }
     * }
     * @response 200 {
     *   "success": true,
     *   "choice_type": "ability_increase",
     *   "asi_choices_remaining": 0,
     *   "changes": {
     *     "feat": null,
     *     "ability_increases": {"STR": 2},
     *     "proficiencies_gained": [],
     *     "spells_gained": []
     *   },
     *   "new_ability_scores": {
     *     "STR": 18, "DEX": 14, "CON": 14, "INT": 10, "WIS": 12, "CHA": 8
     *   }
     * }
     * @response 404 {"message": "Character not found"}
     * @response 422 {"message": "No ASI choices remaining. Level up to gain more."}
     * @response 422 {"message": "Character has already taken this feat."}
     * @response 422 {"message": "Character does not meet feat prerequisites."}
     * @response 422 {"message": "Ability score cannot exceed 20."}
     */
    public function __invoke(AsiChoiceRequest $request, Character $character): JsonResponse
    {
        $choiceType = $request->validated('choice_type');

        if ($choiceType === 'feat') {
            $feat = Feat::findOrFail($request->validated('feat_id'));
            $result = $this->asiChoiceService->applyFeatChoice($character, $feat);
        } else {
            $increases = $request->getAbilityIncreases();
            $result = $this->asiChoiceService->applyAbilityIncrease($character, $increases);
        }

        return response()->json([
            'success' => true,
            'choice_type' => $result->choiceType,
            'asi_choices_remaining' => $result->asiChoicesRemaining,
            'changes' => [
                'feat' => $result->feat,
                'ability_increases' => $result->abilityIncreases,
                'proficiencies_gained' => $result->proficienciesGained,
                'spells_gained' => $result->spellsGained,
            ],
            'new_ability_scores' => $result->newAbilityScores,
        ]);
    }
}
