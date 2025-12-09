<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FeatResource;
use App\Models\Character;
use App\Services\AvailableFeatsService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CharacterAvailableFeatsController extends Controller
{
    public function __construct(
        private AvailableFeatsService $availableFeatsService
    ) {}

    /**
     * List feats available for the character to take.
     *
     * Returns feats the character qualifies for by checking prerequisites.
     * The `source` parameter controls filtering behavior:
     *
     * **Source Types:**
     * - `race` - For Variant Human/Custom Lineage bonus feat. Excludes feats with
     *   ability score prerequisites entirely (can't meet them before scores assigned).
     *   This is RAW compliant and matches D&D Beyond behavior.
     * - `asi` - For level 4+ ASI feat selection. Checks all prerequisites including
     *   ability scores against the character's current stats.
     * - (none) - Same as `asi`, checks all prerequisites.
     *
     * **Examples:**
     * ```
     * GET /api/v1/characters/1/available-feats?source=race  # Variant Human feat
     * GET /api/v1/characters/1/available-feats?source=asi   # Level 4+ ASI feat
     * GET /api/v1/characters/1/available-feats              # Default (same as asi)
     * ```
     *
     * **Prerequisite Checks:**
     * - `Race` - Character's race (or parent race for subraces) must match
     * - `AbilityScore` - Character's score must meet minimum_value (ASI only)
     * - `ProficiencyType` - Character must have proficiency (e.g., Medium Armor)
     * - `Skill` - Character must have skill proficiency (e.g., Athletics)
     * - `CharacterClass` - Character must have the class
     *
     * **Group Logic:**
     * - Prerequisites with same `group_id` use OR logic (any one satisfies)
     * - Prerequisites with different `group_id` use AND logic (all must be satisfied)
     *
     * **Feats excluded for race source (~10 feats):**
     * - Defensive Duelist (DEX 13+)
     * - Grappler (STR 13+)
     * - Inspiring Leader (CHA 13+)
     * - Skulker (DEX 13+)
     * - Ritual Caster variants (INT 13+ OR WIS 13+)
     *
     * @queryParam source string The feat source context. Values: `race`, `asi`. Example: race
     */
    public function __invoke(Request $request, Character $character): AnonymousResourceCollection
    {
        $source = $request->query('source');

        // Validate source parameter
        if ($source !== null && ! in_array($source, ['race', 'asi'], true)) {
            $source = null; // Default to ASI behavior for invalid values
        }

        $feats = $this->availableFeatsService->getAvailableFeats($character, $source);

        return FeatResource::collection($feats);
    }
}
