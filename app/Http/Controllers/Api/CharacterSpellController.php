<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CharacterSpellResource;
use App\Http\Resources\SpellResource;
use App\Http\Resources\SpellSlotsResource;
use App\Models\Character;
use App\Models\Spell;
use App\Services\SpellManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CharacterSpellController extends Controller
{
    public function __construct(
        private SpellManagerService $spellManager
    ) {}

    /**
     * List all spells known by the character
     *
     * Returns spells the character has learned, including preparation status.
     * Spells are categorized by source (class, race, feat, item, other).
     *
     * @x-flow character-creation
     *
     * @x-flow-step 9
     *
     * **Examples:**
     * ```
     * GET /api/v1/characters/1/spells
     * ```
     *
     * **Response includes:**
     * - `spell` object with full spell details
     * - `is_prepared` - True if spell is currently prepared
     * - `is_always_prepared` - True if spell is always prepared (domain, etc.)
     * - `source` - How the spell was acquired (class, race, feat, item, other)
     */
    public function index(Character $character): AnonymousResourceCollection
    {
        $spells = $this->spellManager->getCharacterSpells($character);

        return CharacterSpellResource::collection($spells);
    }

    /**
     * List spells available for the character to learn.
     *
     * Returns spells on the character's class spell list that they haven't learned yet.
     * Optionally filter by maximum spell level.
     * Use include_known=true to include already-learned spells (useful for UI highlighting
     * when user navigates back to spell selection screen).
     *
     * @x-flow character-creation
     *
     * @x-flow-step 8
     *
     * **Examples:**
     * ```
     * GET /api/v1/characters/1/available-spells
     * GET /api/v1/characters/1/available-spells?max_level=3
     * GET /api/v1/characters/1/available-spells?include_known=true
     * GET /api/v1/characters/1/available-spells?max_level=1&include_known=true
     * ```
     */
    public function available(Request $request, Character $character): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'max_level' => ['sometimes', 'integer', 'min:0', 'max:9'],
            'include_known' => ['sometimes', 'in:true,false,1,0'],
        ]);

        $maxLevel = $validated['max_level'] ?? null;
        $includeKnown = filter_var($validated['include_known'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $spells = $this->spellManager->getAvailableSpells($character, $maxLevel, $includeKnown);

        return SpellResource::collection($spells);
    }

    /**
     * Learn a new spell
     *
     * Adds a spell to the character's known spells.
     *
     * @x-flow character-creation
     *
     * @x-flow-step 9
     *
     * **Examples:**
     * ```
     * POST /api/v1/characters/1/spells
     *
     * # Learn from class spell list
     * {"spell": "phb:fireball"}
     *
     * # Learn from a feat
     * {"spell": "phb:magic-missile", "source": "feat"}
     *
     * # Learn from racial trait
     * {"spell": "phb:dancing-lights", "source": "race"}
     * ```
     *
     * **Request Body:**
     * | Field | Type | Required | Description |
     * |-------|------|----------|-------------|
     * | `spell` | string | Yes | Full slug of the spell to learn (e.g., "phb:fireball") |
     * | `source` | string | No | How spell was acquired: class, race, feat, item, other (default: class) |
     *
     * **Validation:**
     * - Spell must be on the character's class spell list (for class source)
     * - Spell level must be accessible at character's level
     * - Spell must not already be known by character
     * - Dangling references allowed per #288
     */
    public function store(Request $request, Character $character): JsonResponse
    {
        // Accept both 'spell' (new API) and 'spell_slug' (backwards compat)
        $validated = $request->validate([
            'spell' => ['required_without:spell_slug', 'string', 'max:150'],
            'spell_slug' => ['required_without:spell', 'string', 'max:150'],
            'source' => ['sometimes', 'string', 'in:class,race,feat,item,other'],
        ]);

        $spellSlug = $validated['spell'] ?? $validated['spell_slug'];
        $spell = Spell::where('full_slug', $spellSlug)->first();
        $source = $validated['source'] ?? 'class';

        // Check for duplicates (works with or without spell entity)
        if ($character->spells()->where('spell_slug', $spellSlug)->exists()) {
            return response()->json([
                'message' => 'Character already knows this spell.',
                'errors' => ['spell' => ['Character already knows this spell.']],
            ], 422);
        }

        if ($spell) {
            // Spell exists - use spell manager for proper validation
            $characterSpell = $this->spellManager->learnSpell($character, $spell, $source);
        } else {
            // Dangling reference - create with slug only per #288
            $characterSpell = $character->spells()->create([
                'spell_slug' => $spellSlug,
                'source' => $source,
            ]);
        }

        $characterSpell->load('spell.spellSchool');

        return (new CharacterSpellResource($characterSpell))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Remove a spell from the character's known spells
     *
     * Forgets a spell the character previously learned. Accepts either spell ID or slug.
     *
     * **Examples:**
     * ```
     * DELETE /api/v1/characters/1/spells/123         # Remove by ID
     * DELETE /api/v1/characters/1/spells/fireball    # Remove by slug
     * ```
     *
     * **Note:** Always-prepared spells (from domain, subclass, etc.) cannot be removed
     * without removing the source that granted them.
     *
     * @param  Character  $character  The character
     * @param  string  $spellIdOrSlug  Spell ID or slug
     * @return Response 204 on success
     */
    public function destroy(Character $character, string $spellIdOrSlug): Response
    {
        $spell = is_numeric($spellIdOrSlug)
            ? Spell::findOrFail($spellIdOrSlug)
            : Spell::where('full_slug', $spellIdOrSlug)->orWhere('slug', $spellIdOrSlug)->firstOrFail();

        $this->spellManager->forgetSpell($character, $spell);

        return response()->noContent();
    }

    /**
     * Prepare a spell for casting
     *
     * Changes a spell's status from 'known' to 'prepared'. Prepared casters
     * (Cleric, Druid, Paladin, Wizard) must prepare spells to cast them.
     * Accepts either spell ID or slug.
     *
     * **Examples:**
     * ```
     * PATCH /api/v1/characters/1/spells/123/prepare         # Prepare by ID
     * PATCH /api/v1/characters/1/spells/fireball/prepare    # Prepare by slug
     * ```
     *
     * **D&D 5e Preparation Rules:**
     * - Cantrips cannot be prepared (they're always ready)
     * - Preparation limit = class level + spellcasting modifier
     * - Prepared spells can be changed after a long rest
     *
     * @param  Character  $character  The character
     * @param  string  $spellIdOrSlug  Spell ID or slug
     */
    public function prepare(Character $character, string $spellIdOrSlug): CharacterSpellResource
    {
        $spell = is_numeric($spellIdOrSlug)
            ? Spell::findOrFail($spellIdOrSlug)
            : Spell::where('full_slug', $spellIdOrSlug)->orWhere('slug', $spellIdOrSlug)->firstOrFail();

        $characterSpell = $this->spellManager->prepareSpell($character, $spell);
        $characterSpell->load('spell.spellSchool');

        return new CharacterSpellResource($characterSpell);
    }

    /**
     * Unprepare a spell
     *
     * Changes a spell's status from 'prepared' to 'known'.
     * Frees up a preparation slot for another spell.
     * Accepts either spell ID or slug.
     *
     * **Examples:**
     * ```
     * PATCH /api/v1/characters/1/spells/123/unprepare         # Unprepare by ID
     * PATCH /api/v1/characters/1/spells/fireball/unprepare    # Unprepare by slug
     * ```
     *
     * **Restrictions:**
     * - Always-prepared spells (from domain, subclass features, etc.) cannot be unprepared
     * - Cantrips cannot be unprepared (they don't use preparation slots)
     *
     * @param  Character  $character  The character
     * @param  string  $spellIdOrSlug  Spell ID or slug
     */
    public function unprepare(Character $character, string $spellIdOrSlug): CharacterSpellResource
    {
        $spell = is_numeric($spellIdOrSlug)
            ? Spell::findOrFail($spellIdOrSlug)
            : Spell::where('full_slug', $spellIdOrSlug)->orWhere('slug', $spellIdOrSlug)->firstOrFail();

        $characterSpell = $this->spellManager->unprepareSpell($character, $spell);
        $characterSpell->load('spell.spellSchool');

        return new CharacterSpellResource($characterSpell);
    }

    /**
     * Get spell slot information for the character.
     *
     * Returns consolidated spell slot data including calculated maximums,
     * tracked usage (spent slots), and available slots. Also includes
     * preparation limit for prepared casters and current prepared count.
     *
     * @x-flow character-creation
     *
     * @x-flow-step 9
     *
     * **Examples:**
     * ```
     * GET /api/v1/characters/1/spell-slots
     * ```
     *
     * **Response Structure:**
     * ```json
     * {
     *   "data": {
     *     "slots": {
     *       "1": { "total": 4, "spent": 2, "available": 2 },
     *       "2": { "total": 3, "spent": 1, "available": 2 }
     *     },
     *     "pact_magic": null,
     *     "preparation_limit": 6,
     *     "prepared_count": 3
     *   }
     * }
     * ```
     *
     * **For Warlocks (Pact Magic):**
     * ```json
     * {
     *   "data": {
     *     "slots": {},
     *     "pact_magic": {
     *       "level": 2,
     *       "total": 2,
     *       "spent": 0,
     *       "available": 2
     *     },
     *     "preparation_limit": null,
     *     "prepared_count": 0
     *   }
     * }
     * ```
     *
     * **Fields:**
     * - `slots` - Standard spell slots by level (1-9) with total/spent/available
     * - `pact_magic` - Warlock pact magic slots (level/total/spent/available) or null
     * - `preparation_limit` - Max spells that can be prepared (for prepared casters) or null
     * - `prepared_count` - Current number of prepared spells
     *
     * **Slot Tracking:**
     * - If no usage has been tracked, `spent` will be 0 and `available` equals `total`
     * - Spent slots are tracked in the `character_spell_slots` table
     * - Use `POST /spell-slots/use` to expend a slot
     */
    public function slots(Character $character): SpellSlotsResource
    {
        $slotData = $this->spellManager->getSpellSlots($character);

        return new SpellSlotsResource($slotData);
    }
}
