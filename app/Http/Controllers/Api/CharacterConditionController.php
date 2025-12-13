<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Character\Condition\CharacterConditionStoreRequest;
use App\Http\Resources\CharacterConditionResource;
use App\Models\Character;
use App\Models\CharacterCondition;
use App\Models\Condition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CharacterConditionController extends Controller
{
    /**
     * List all active conditions for a character
     *
     * Returns all conditions currently affecting the character, including exhaustion levels.
     * Conditions include D&D 5e status effects like Blinded, Charmed, Frightened, etc.
     *
     * **Examples:**
     * ```
     * GET /api/v1/characters/1/conditions   # List all active conditions
     * ```
     *
     * **Available Conditions (15 total):**
     * - `blinded` - Can't see, auto-fail sight checks
     * - `charmed` - Can't attack charmer, charmer has advantage on social checks
     * - `deafened` - Can't hear, auto-fail hearing checks
     * - `frightened` - Disadvantage on checks while source visible
     * - `grappled` - Speed becomes 0
     * - `incapacitated` - Can't take actions or reactions
     * - `invisible` - Heavily obscured for hiding
     * - `paralyzed` - Incapacitated, auto-fail STR/DEX saves
     * - `petrified` - Turned to stone, weight x10
     * - `poisoned` - Disadvantage on attacks and ability checks
     * - `prone` - Disadvantage on attacks, must crawl or stand
     * - `restrained` - Speed 0, disadvantage on DEX saves
     * - `stunned` - Incapacitated, can't move, only faltering speech
     * - `unconscious` - Incapacitated, unaware, drops items
     * - `exhaustion` - Cumulative levels 1-6, level 6 = death
     *
     * **Response includes:**
     * - `condition` object with id, name, slug
     * - `level` (only for exhaustion, null otherwise)
     * - `source` (optional string indicating how condition was applied)
     * - `duration` (optional string like "1 minute" or "until long rest")
     * - `is_exhaustion` (boolean flag)
     * - `exhaustion_warning` (warning message at level 6)
     */
    public function index(Character $character): AnonymousResourceCollection
    {
        $conditions = $character->conditions()->with('condition')->get();

        return CharacterConditionResource::collection($conditions);
    }

    /**
     * Add or update a condition on a character
     *
     * Applies a condition to the character or updates an existing one. Uses upsert logic:
     * if the character already has the condition, it updates the source/duration/level.
     *
     * @x-flow gameplay-combat
     *
     * **Examples:**
     * ```
     * POST /api/v1/characters/1/conditions
     *
     * # Apply poisoned condition from a monster attack
     * {"condition_id": 10, "source": "Giant Spider bite", "duration": "1 minute"}
     *
     * # Apply frightened condition from a spell
     * {"condition_id": 4, "source": "Cause Fear spell", "duration": "concentration, 1 minute"}
     *
     * # Apply exhaustion level 1 from forced march
     * {"condition_id": 15, "level": 1, "source": "Forced march"}
     *
     * # Increase exhaustion to level 2 (updates existing)
     * {"condition_id": 15, "level": 2, "source": "No food for 24 hours"}
     * ```
     *
     * **Request Body:**
     * | Field | Type | Required | Description |
     * |-------|------|----------|-------------|
     * | `condition_id` | integer | Yes | ID of the condition (1-15, see index endpoint) |
     * | `level` | integer | No | Exhaustion level 1-6 (only valid for exhaustion, condition_id=15) |
     * | `source` | string | No | What caused this condition (max 255 chars) |
     * | `duration` | string | No | How long it lasts, e.g. "1 minute", "until long rest" (max 255 chars) |
     *
     * **Exhaustion Special Handling:**
     * - Only the exhaustion condition (condition_id=15) accepts a `level` parameter
     * - Level must be 1-6 (6 = death)
     * - If level omitted when adding new exhaustion: defaults to 1
     * - If level omitted when updating existing: preserves current level
     * - Validation error if level provided for non-exhaustion conditions
     *
     * **Exhaustion Level Effects (D&D 5e):**
     * - Level 1: Disadvantage on ability checks
     * - Level 2: Speed halved
     * - Level 3: Disadvantage on attack rolls and saving throws
     * - Level 4: Hit point maximum halved
     * - Level 5: Speed reduced to 0
     * - Level 6: Death
     *
     * **Source Values (suggestions):**
     * - `"Spell: {spell name}"` - From a spell effect
     * - `"Monster: {monster name}"` - From a creature attack
     * - `"Item: {item name}"` - From a magical item
     * - `"Environmental"` - From environmental hazard
     * - `"Forced march"`, `"No food"`, `"No water"` - Common exhaustion sources
     */
    public function store(CharacterConditionStoreRequest $request, Character $character): CharacterConditionResource
    {
        $condition = Condition::where('slug', $request->condition_slug)->firstOrFail();
        $isExhaustion = str_ends_with($condition->slug, ':exhaustion');

        // Determine level - only set for exhaustion
        // If updating existing exhaustion without specifying level, preserve current level
        // If adding new exhaustion without specifying level, default to 1
        $level = null;
        if ($isExhaustion) {
            if ($request->has('level')) {
                $level = $request->level;
            } else {
                $existingLevel = CharacterCondition::where('character_id', $character->id)
                    ->where('condition_slug', $condition->slug)
                    ->value('level');
                $level = $existingLevel ?? 1;
            }
        }

        // Upsert - update if exists, create if not
        $characterCondition = CharacterCondition::updateOrCreate(
            [
                'character_id' => $character->id,
                'condition_slug' => $condition->slug,
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
     * Remove a condition from a character
     *
     * Removes the specified condition from the character. Accepts either condition ID or slug.
     * For exhaustion, this removes all levels (full recovery).
     *
     * @x-flow gameplay-combat
     *
     * **Examples:**
     * ```
     * DELETE /api/v1/characters/1/conditions/10        # Remove by condition ID
     * DELETE /api/v1/characters/1/conditions/poisoned  # Remove by slug
     * DELETE /api/v1/characters/1/conditions/exhaustion # Remove all exhaustion levels
     * ```
     *
     * **Condition IDs and Slugs:**
     * | ID | Slug | Name |
     * |----|------|------|
     * | 1 | blinded | Blinded |
     * | 2 | charmed | Charmed |
     * | 3 | deafened | Deafened |
     * | 4 | frightened | Frightened |
     * | 5 | grappled | Grappled |
     * | 6 | incapacitated | Incapacitated |
     * | 7 | invisible | Invisible |
     * | 8 | paralyzed | Paralyzed |
     * | 9 | petrified | Petrified |
     * | 10 | poisoned | Poisoned |
     * | 11 | prone | Prone |
     * | 12 | restrained | Restrained |
     * | 13 | stunned | Stunned |
     * | 14 | unconscious | Unconscious |
     * | 15 | exhaustion | Exhaustion |
     *
     * **Use Cases:**
     * - Spell ending: Character's frightened condition from spell wears off
     * - Healing: Lesser Restoration removes poisoned
     * - Rest: Long rest removes exhaustion (or use store to reduce level)
     * - Combat end: Remove grappled when grappler is defeated
     *
     * @param  Character  $character  The character to remove the condition from
     * @param  string  $conditionIdOrSlug  Condition ID (1-15) or slug (e.g., "poisoned")
     * @return JsonResponse 204 on success
     */
    public function destroy(Character $character, string $conditionIdOrSlug): JsonResponse
    {
        // Find by ID or slug
        $conditionModel = is_numeric($conditionIdOrSlug)
            ? Condition::findOrFail($conditionIdOrSlug)
            : Condition::where('slug', $conditionIdOrSlug)->firstOrFail();

        $deleted = $character->conditions()
            ->where('condition_slug', $conditionModel->slug)
            ->delete();

        if ($deleted === 0) {
            abort(404, 'Character does not have this condition');
        }

        return response()->json(null, 204);
    }
}
