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
     *
     * @return AnonymousResourceCollection<CharacterSpellResource>
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
     * **Examples:**
     * ```
     * POST /api/v1/characters/1/spells
     *
     * # Learn from class spell list
     * {"spell_id": 123}
     *
     * # Learn from a feat
     * {"spell_id": 123, "source": "feat"}
     *
     * # Learn from racial trait
     * {"spell_id": 456, "source": "race"}
     * ```
     *
     * **Request Body:**
     * | Field | Type | Required | Description |
     * |-------|------|----------|-------------|
     * | `spell_id` | integer | Yes | ID of the spell to learn |
     * | `source` | string | No | How spell was acquired: class, race, feat, item, other (default: class) |
     *
     * **Validation:**
     * - Spell must exist in database
     * - Spell must be on the character's class spell list (for class source)
     * - Spell level must be accessible at character's level
     * - Spell must not already be known by character
     *
     *
     * @response 201 CharacterSpellResource
     * @response 404 array{message: string} Spell not found
     * @response 422 array{message: string, errors: array{spell_id?: string[]}} Validation error or spell already known
     */
    public function store(Request $request, Character $character): JsonResponse
    {
        $validated = $request->validate([
            'spell_id' => ['required', 'exists:spells,id'],
            'source' => ['sometimes', 'string', 'in:class,race,feat,item,other'],
        ]);

        $spell = Spell::findOrFail($validated['spell_id']);
        $source = $validated['source'] ?? 'class';

        $characterSpell = $this->spellManager->learnSpell($character, $spell, $source);
        $characterSpell->load('spell.spellSchool');

        return (new CharacterSpellResource($characterSpell))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Remove a spell from the character's known spells
     *
     * Forgets a spell the character previously learned.
     *
     * **Examples:**
     * ```
     * DELETE /api/v1/characters/1/spells/123
     * ```
     *
     * **Note:** Always-prepared spells (from domain, subclass, etc.) cannot be removed
     * without removing the source that granted them.
     *
     * @param  Character  $character  The character
     * @param  Spell  $spell  The spell to forget
     * @return Response 204 on success
     *
     * @response 204 No content on success
     * @response 404 array{message: string} Character doesn't know this spell
     */
    public function destroy(Character $character, Spell $spell): Response
    {
        $this->spellManager->forgetSpell($character, $spell);

        return response()->noContent();
    }

    /**
     * Prepare a spell for casting
     *
     * Changes a spell's status from 'known' to 'prepared'. Prepared casters
     * (Cleric, Druid, Paladin, Wizard) must prepare spells to cast them.
     *
     * **Examples:**
     * ```
     * PATCH /api/v1/characters/1/spells/123/prepare
     * ```
     *
     * **D&D 5e Preparation Rules:**
     * - Cantrips cannot be prepared (they're always ready)
     * - Preparation limit = class level + spellcasting modifier
     * - Prepared spells can be changed after a long rest
     *
     * @param  Character  $character  The character
     * @param  Spell  $spell  The spell to prepare
     *
     * @response 200 CharacterSpellResource with is_prepared: true
     * @response 404 array{message: string} Character doesn't know this spell
     * @response 422 array{message: string} Spell cannot be prepared (cantrip or already prepared)
     */
    public function prepare(Character $character, Spell $spell): CharacterSpellResource
    {
        $characterSpell = $this->spellManager->prepareSpell($character, $spell);
        $characterSpell->load('spell.spellSchool');

        return new CharacterSpellResource($characterSpell);
    }

    /**
     * Unprepare a spell
     *
     * Changes a spell's status from 'prepared' to 'known'.
     * Frees up a preparation slot for another spell.
     *
     * **Examples:**
     * ```
     * PATCH /api/v1/characters/1/spells/123/unprepare
     * ```
     *
     * **Restrictions:**
     * - Always-prepared spells (from domain, subclass features, etc.) cannot be unprepared
     * - Cantrips cannot be unprepared (they don't use preparation slots)
     *
     * @param  Character  $character  The character
     * @param  Spell  $spell  The spell to unprepare
     *
     * @response 200 CharacterSpellResource with is_prepared: false
     * @response 404 array{message: string} Character doesn't know this spell
     * @response 422 array{message: string} Spell cannot be unprepared (always-prepared)
     */
    public function unprepare(Character $character, Spell $spell): CharacterSpellResource
    {
        $characterSpell = $this->spellManager->unprepareSpell($character, $spell);
        $characterSpell->load('spell.spellSchool');

        return new CharacterSpellResource($characterSpell);
    }

    /**
     * Get spell slot information for the character.
     *
     * Returns available spell slots by level and the preparation limit.
     *
     * **Examples:**
     * ```
     * GET /api/v1/characters/1/spell-slots
     * ```
     */
    public function slots(Character $character): SpellSlotsResource
    {
        $slotData = $this->spellManager->getSpellSlots($character);

        return new SpellSlotsResource($slotData);
    }
}
