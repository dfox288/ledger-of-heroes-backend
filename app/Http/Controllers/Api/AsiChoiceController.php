<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Character\CharacterAsiChoiceRequest;
use App\Http\Resources\AsiChoiceResource;
use App\Models\Character;
use App\Models\Feat;
use App\Services\AsiChoiceService;

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
     * @x-flow gameplay-level-up
     *
     * @group Character Builder
     *
     * @urlParam character integer required The character ID. Example: 1
     *
     * @bodyParam choice_type string required Either "feat" or "ability_increase". Example: feat
     * @bodyParam feat_id integer Feat ID (required if choice_type is feat). Example: 42
     * @bodyParam ability_increases object Ability increases (required if choice_type is ability_increase). Example: {"STR": 2}
     */
    public function __invoke(CharacterAsiChoiceRequest $request, Character $character): AsiChoiceResource
    {
        $choiceType = $request->validated('choice_type');

        if ($choiceType === 'feat') {
            $feat = Feat::findOrFail($request->validated('feat_id'));
            $result = $this->asiChoiceService->applyFeatChoice($character, $feat);
        } else {
            $increases = $request->getAbilityIncreases();
            $result = $this->asiChoiceService->applyAbilityIncrease($character, $increases);
        }

        return new AsiChoiceResource($result);
    }
}
