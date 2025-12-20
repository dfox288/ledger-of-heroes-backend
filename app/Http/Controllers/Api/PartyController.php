<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Party\PartyAddCharacterRequest;
use App\Http\Requests\Party\PartyIndexRequest;
use App\Http\Requests\Party\PartyShowRequest;
use App\Http\Requests\Party\PartyStatsRequest;
use App\Http\Requests\Party\PartyStoreRequest;
use App\Http\Requests\Party\PartyUpdateRequest;
use App\Http\Resources\PartyResource;
use App\Http\Resources\PartyStatsResource;
use App\Models\Character;
use App\Models\Party;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PartyController extends Controller
{
    /**
     * List all parties.
     *
     * TODO: Re-add user scoping when auth is implemented.
     */
    public function index(PartyIndexRequest $request): AnonymousResourceCollection
    {
        $parties = Party::withCount('characters')
            ->orderBy('updated_at', 'desc')
            ->get();

        return PartyResource::collection($parties);
    }

    /**
     * Create a new party.
     *
     * TODO: Re-add user_id from auth when auth is implemented.
     */
    public function store(PartyStoreRequest $request): JsonResponse
    {
        $party = Party::create([
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'user_id' => $request->user()?->id,
        ]);

        return (new PartyResource($party))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Show a party with its characters.
     *
     * TODO: Re-add ownership check when auth is implemented.
     */
    public function show(PartyShowRequest $request, Party $party): PartyResource
    {
        $party->load([
            'characters.characterClasses.characterClass',
            'characters.media',
        ]);

        return new PartyResource($party);
    }

    /**
     * Update a party.
     *
     * TODO: Re-add ownership check when auth is implemented.
     */
    public function update(PartyUpdateRequest $request, Party $party): PartyResource
    {
        $party->update($request->validated());

        return new PartyResource($party);
    }

    /**
     * Delete a party.
     *
     * TODO: Re-add ownership check when auth is implemented.
     */
    public function destroy(Party $party): Response
    {
        $party->delete();

        return response()->noContent();
    }

    /**
     * Add a character to a party.
     *
     * TODO: Re-add ownership check when auth is implemented.
     */
    public function addCharacter(PartyAddCharacterRequest $request, Party $party): JsonResponse
    {
        $party->characters()->attach($request->validated('character_id'), [
            'joined_at' => now(),
        ]);

        $party->load([
            'characters.characterClasses.characterClass',
            'characters.media',
        ]);

        return (new PartyResource($party))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Remove a character from a party.
     *
     * TODO: Re-add ownership check when auth is implemented.
     */
    public function removeCharacter(Party $party, Character $character): Response
    {
        // Check if character is in party
        if (! $party->characters()->where('character_id', $character->id)->exists()) {
            abort(404, 'Character not in party');
        }

        $party->characters()->detach($character->id);

        return response()->noContent();
    }

    /**
     * Get aggregated stats for all characters in a party (DM dashboard).
     *
     * TODO: Re-add ownership check when auth is implemented.
     */
    public function stats(PartyStatsRequest $request, Party $party): PartyStatsResource
    {
        // Load characters with relationships needed for stats
        $party->load([
            'characters.characterClasses.characterClass',
            'characters.conditions.condition',
            'characters.proficiencies.skill',
            'characters.proficiencies.proficiencyType',
            'characters.spellSlots',
            // Phase 1: Combat
            'characters.race',
            // Phase 2: Senses & Capabilities
            'characters.race.senses.sense',
            'characters.race.size',
            'characters.sizeChoice',
            'characters.languages.language',
            // Phase 3: Party summary needs spells
            'characters.spells.spell',
            // Phase 4: Equipment
            'characters.equipment.item.itemType',
            // Phase 5: Class counters (Rage, Ki Points, etc.)
            'characters.features.feature.characterClass',
        ]);

        return new PartyStatsResource($party);
    }
}
