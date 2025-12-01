<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CharacterSpellResource;
use App\Http\Resources\SpellResource;
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
     * List all spells known by the character.
     *
     * Returns spells the character has learned, including preparation status.
     *
     * **Examples:**
     * ```
     * GET /api/v1/characters/1/spells
     * ```
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
     *
     * **Examples:**
     * ```
     * GET /api/v1/characters/1/available-spells
     * GET /api/v1/characters/1/available-spells?max_level=3
     * ```
     */
    public function available(Request $request, Character $character): AnonymousResourceCollection
    {
        $maxLevel = $request->query('max_level') !== null
            ? (int) $request->query('max_level')
            : null;

        $spells = $this->spellManager->getAvailableSpells($character, $maxLevel);

        return SpellResource::collection($spells);
    }

    /**
     * Learn a new spell.
     *
     * Adds a spell to the character's known spells.
     *
     * **Examples:**
     * ```
     * POST /api/v1/characters/1/spells {"spell_id": 123}
     * ```
     *
     * **Validation:**
     * - Spell must be on the character's class spell list
     * - Spell level must be accessible at character's level
     * - Spell must not already be known
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
     * Remove a spell from the character's known spells.
     *
     * **Examples:**
     * ```
     * DELETE /api/v1/characters/1/spells/123
     * ```
     */
    public function destroy(Character $character, Spell $spell): Response
    {
        $this->spellManager->forgetSpell($character, $spell);

        return response()->noContent();
    }

    /**
     * Prepare a spell for casting.
     *
     * Changes a spell's status from 'known' to 'prepared'.
     * Cantrips cannot be prepared (they're always ready).
     *
     * **Examples:**
     * ```
     * PATCH /api/v1/characters/1/spells/123/prepare
     * ```
     */
    public function prepare(Character $character, Spell $spell): CharacterSpellResource
    {
        $characterSpell = $this->spellManager->prepareSpell($character, $spell);
        $characterSpell->load('spell.spellSchool');

        return new CharacterSpellResource($characterSpell);
    }

    /**
     * Unprepare a spell.
     *
     * Changes a spell's status from 'prepared' to 'known'.
     * Always-prepared spells (from domain, etc.) cannot be unprepared.
     *
     * **Examples:**
     * ```
     * PATCH /api/v1/characters/1/spells/123/unprepare
     * ```
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
    public function slots(Character $character): JsonResponse
    {
        $slotData = $this->spellManager->getSpellSlots($character);

        return response()->json(['data' => $slotData]);
    }
}
