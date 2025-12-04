<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CharacterCondition\StoreCharacterConditionRequest;
use App\Http\Resources\CharacterConditionResource;
use App\Models\Character;
use App\Models\CharacterCondition;
use App\Models\Condition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CharacterConditionController extends Controller
{
    /**
     * List all active conditions for a character.
     */
    public function index(Character $character): AnonymousResourceCollection
    {
        $conditions = $character->conditions()->with('condition')->get();

        return CharacterConditionResource::collection($conditions);
    }

    /**
     * Add or update a condition on a character.
     */
    public function store(StoreCharacterConditionRequest $request, Character $character): CharacterConditionResource
    {
        $condition = Condition::findOrFail($request->condition_id);
        $isExhaustion = $condition->slug === 'exhaustion';

        // Determine level - only set for exhaustion
        // If updating existing exhaustion without specifying level, preserve current level
        // If adding new exhaustion without specifying level, default to 1
        $level = null;
        if ($isExhaustion) {
            if ($request->has('level')) {
                $level = $request->level;
            } else {
                $existingLevel = CharacterCondition::where('character_id', $character->id)
                    ->where('condition_id', $condition->id)
                    ->value('level');
                $level = $existingLevel ?? 1;
            }
        }

        // Upsert - update if exists, create if not
        $characterCondition = CharacterCondition::updateOrCreate(
            [
                'character_id' => $character->id,
                'condition_id' => $condition->id,
            ],
            [
                'level' => $level,
                'source' => $request->source,
                'duration' => $request->duration,
            ]
        );

        $characterCondition->load('condition');

        return new CharacterConditionResource($characterCondition);
    }

    /**
     * Remove a condition from a character.
     */
    public function destroy(Character $character, string $conditionIdOrSlug): JsonResponse
    {
        // Find by ID or slug
        $conditionModel = is_numeric($conditionIdOrSlug)
            ? Condition::findOrFail($conditionIdOrSlug)
            : Condition::where('slug', $conditionIdOrSlug)->firstOrFail();

        $deleted = $character->conditions()
            ->where('condition_id', $conditionModel->id)
            ->delete();

        if ($deleted === 0) {
            abort(404, 'Character does not have this condition');
        }

        return response()->json(null, 204);
    }
}
