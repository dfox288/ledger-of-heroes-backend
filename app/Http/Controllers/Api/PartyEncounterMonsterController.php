<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Party\PartyAddMonsterRequest;
use App\Http\Requests\Party\PartyUpdateMonsterRequest;
use App\Http\Resources\EncounterMonsterResource;
use App\Models\EncounterMonster;
use App\Models\Monster;
use App\Models\Party;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PartyEncounterMonsterController extends Controller
{
    /**
     * List all monsters in the party's encounter.
     */
    public function index(Request $request, Party $party): AnonymousResourceCollection
    {
        $monsters = $party->encounterMonsters()
            ->with(['monster.actions'])
            ->get();

        return EncounterMonsterResource::collection($monsters);
    }

    /**
     * Add monster(s) to the party's encounter.
     */
    public function store(PartyAddMonsterRequest $request, Party $party): JsonResponse
    {
        $monster = Monster::findOrFail($request->validated('monster_id'));
        $quantity = $request->validated('quantity', 1);

        // Find the highest existing number for this monster type in this party
        $highestNumber = $this->getHighestLabelNumber($party, $monster);

        $created = collect();
        for ($i = 1; $i <= $quantity; $i++) {
            $encounterMonster = $party->encounterMonsters()->create([
                'monster_id' => $monster->id,
                'label' => $monster->name.' '.($highestNumber + $i),
                'current_hp' => $monster->hit_points_average,
                'max_hp' => $monster->hit_points_average,
            ]);
            $created->push($encounterMonster);
        }

        // Load relationships for response
        $created->each(fn ($em) => $em->load(['monster.actions']));

        return EncounterMonsterResource::collection($created)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Update a monster instance (HP, label).
     */
    public function update(PartyUpdateMonsterRequest $request, Party $party, EncounterMonster $encounterMonster): JsonResponse|EncounterMonsterResource
    {
        // Verify the monster belongs to this party
        if ($encounterMonster->party_id !== $party->id) {
            return response()->json(['message' => 'Monster not found in this party'], Response::HTTP_NOT_FOUND);
        }

        $encounterMonster->update($request->validated());
        $encounterMonster->load(['monster.actions']);

        return new EncounterMonsterResource($encounterMonster);
    }

    /**
     * Remove a single monster from the encounter.
     */
    public function destroy(Request $request, Party $party, EncounterMonster $encounterMonster): JsonResponse
    {
        // Verify the monster belongs to this party
        if ($encounterMonster->party_id !== $party->id) {
            return response()->json(['message' => 'Monster not found in this party'], Response::HTTP_NOT_FOUND);
        }

        $encounterMonster->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Clear all monsters from the party's encounter.
     */
    public function clear(Request $request, Party $party): JsonResponse
    {
        $party->encounterMonsters()->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Get the highest label number for a monster type in this party.
     */
    private function getHighestLabelNumber(Party $party, Monster $monster): int
    {
        $pattern = preg_quote($monster->name, '/').' (\d+)';

        $highestNumber = 0;

        $party->encounterMonsters()
            ->where('monster_id', $monster->id)
            ->pluck('label')
            ->each(function ($label) use ($pattern, &$highestNumber) {
                if (preg_match('/'.$pattern.'$/', $label, $matches)) {
                    $number = (int) $matches[1];
                    if ($number > $highestNumber) {
                        $highestNumber = $number;
                    }
                }
            });

        return $highestNumber;
    }
}
