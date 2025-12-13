<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Party\PartyAddCharacterRequest;
use App\Http\Requests\Party\PartyStoreRequest;
use App\Http\Requests\Party\PartyUpdateRequest;
use App\Http\Resources\PartyResource;
use App\Models\Character;
use App\Models\Party;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PartyController extends Controller
{
    /**
     * List all parties for the authenticated user.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $parties = Party::where('user_id', $request->user()->id)
            ->withCount('characters')
            ->orderBy('updated_at', 'desc')
            ->get();

        return PartyResource::collection($parties);
    }

    /**
     * Create a new party.
     */
    public function store(PartyStoreRequest $request): JsonResponse
    {
        $party = Party::create([
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'user_id' => $request->user()->id,
        ]);

        return (new PartyResource($party))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Show a party with its characters.
     */
    public function show(Request $request, Party $party): PartyResource|JsonResponse
    {
        // Check ownership
        if ($party->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Party not found'], Response::HTTP_NOT_FOUND);
        }

        $party->load('characters');

        return new PartyResource($party);
    }

    /**
     * Update a party.
     */
    public function update(PartyUpdateRequest $request, Party $party): PartyResource|JsonResponse
    {
        // Check ownership
        if ($party->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Party not found'], Response::HTTP_NOT_FOUND);
        }

        $party->update($request->validated());

        return new PartyResource($party);
    }

    /**
     * Delete a party.
     */
    public function destroy(Request $request, Party $party): JsonResponse
    {
        // Check ownership
        if ($party->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Party not found'], Response::HTTP_NOT_FOUND);
        }

        $party->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Add a character to a party.
     */
    public function addCharacter(PartyAddCharacterRequest $request, Party $party): JsonResponse
    {
        // Check ownership
        if ($party->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Party not found'], Response::HTTP_NOT_FOUND);
        }

        $party->characters()->attach($request->validated('character_id'), [
            'joined_at' => now(),
        ]);

        return response()->json(['message' => 'Character added to party'], Response::HTTP_CREATED);
    }

    /**
     * Remove a character from a party.
     */
    public function removeCharacter(Request $request, Party $party, Character $character): JsonResponse
    {
        // Check ownership
        if ($party->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Party not found'], Response::HTTP_NOT_FOUND);
        }

        // Check if character is in party
        if (! $party->characters()->where('character_id', $character->id)->exists()) {
            return response()->json(['message' => 'Character not in party'], Response::HTTP_NOT_FOUND);
        }

        $party->characters()->detach($character->id);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
