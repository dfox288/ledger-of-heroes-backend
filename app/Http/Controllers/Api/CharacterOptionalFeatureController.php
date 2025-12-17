<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CharacterOptionalFeatureResource;
use App\Models\Character;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CharacterOptionalFeatureController extends Controller
{
    /**
     * List all optional features for a character.
     *
     * Returns the character's selected optional features (invocations, infusions,
     * metamagic, fighting styles, etc.) with full details including descriptions.
     *
     * **Examples:**
     * ```
     * GET /api/v1/characters/1/optional-features
     * GET /api/v1/characters/silent-knight-vWCB/optional-features
     * ```
     *
     * **Response:**
     * ```json
     * {
     *   "data": [
     *     {
     *       "id": 1,
     *       "slug": "phb:agonizing-blast",
     *       "name": "Agonizing Blast",
     *       "feature_type": "eldritch_invocation",
     *       "feature_type_label": "Eldritch Invocation",
     *       "description": "When you cast eldritch blast...",
     *       "class_slug": "phb:warlock",
     *       "level_acquired": 2
     *     }
     *   ]
     * }
     * ```
     *
     * @response AnonymousResourceCollection<CharacterOptionalFeatureResource>
     */
    public function index(Character $character): AnonymousResourceCollection
    {
        $featureSelections = $character->featureSelections()
            ->with([
                'optionalFeature.spellSchool',
                'optionalFeature.classes',
                'optionalFeature.sources.source',
                'optionalFeature.tags',
                'optionalFeature.prerequisites',
            ])
            ->get()
            ->filter(fn ($selection) => $selection->optionalFeature !== null);

        return CharacterOptionalFeatureResource::collection($featureSelections);
    }
}
