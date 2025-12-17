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
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PartyEncounterMonsterController extends Controller
{
    /**
     * List all monsters in the party's encounter.
     *
     * TODO: Re-add ownership check when auth is implemented.
     */
    public function index(Party $party): AnonymousResourceCollection
    {
        $monsters = $party->encounterMonsters()
            ->with([
                'monster.actions' => fn ($q) => $q->where('action_type', '!=', 'reaction'),
                'monster.legendaryActions',
            ])
            ->get();

        return EncounterMonsterResource::collection($monsters);
    }

    /**
     * Add monster(s) to the party's encounter.
     *
     * TODO: Re-add ownership check when auth is implemented.
     */
    public function store(PartyAddMonsterRequest $request, Party $party): JsonResponse
    {
        // Monster model needed for name and hit_points_average
        $monster = Monster::findOrFail($request->validated('monster_id'));
        $quantity = $request->validated('quantity', 1);

        // Find the highest existing number for this monster type in this party
        $highestNumber = $this->getHighestLabelNumber($party, $monster);

        $createdIds = [];
        for ($i = 1; $i <= $quantity; $i++) {
            $encounterMonster = $party->encounterMonsters()->create([
                'monster_id' => $monster->id,
                'label' => $monster->name.' '.($highestNumber + $i),
                'current_hp' => $monster->hit_points_average,
                'max_hp' => $monster->hit_points_average,
            ]);
            $createdIds[] = $encounterMonster->id;
        }

        // Fetch created monsters with eager-loaded relationships
        $created = EncounterMonster::whereIn('id', $createdIds)
            ->with([
                'monster.actions' => fn ($q) => $q->where('action_type', '!=', 'reaction'),
                'monster.legendaryActions',
            ])
            ->get();

        return EncounterMonsterResource::collection($created)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Update a monster instance (HP, label).
     *
     * TODO: Re-add ownership check when auth is implemented.
     */
    public function update(PartyUpdateMonsterRequest $request, Party $party, EncounterMonster $encounterMonster): EncounterMonsterResource
    {
        // Verify the monster belongs to this party
        if ($encounterMonster->party_id !== $party->id) {
            abort(404, 'Monster not found in this party');
        }

        $encounterMonster->update($request->validated());
        $encounterMonster->load([
            'monster.actions' => fn ($q) => $q->where('action_type', '!=', 'reaction'),
            'monster.legendaryActions',
        ]);

        return new EncounterMonsterResource($encounterMonster);
    }

    /**
     * Remove a single monster from the encounter.
     *
     * TODO: Re-add ownership check when auth is implemented.
     */
    public function destroy(Party $party, EncounterMonster $encounterMonster): Response
    {
        // Verify the monster belongs to this party
        if ($encounterMonster->party_id !== $party->id) {
            abort(404, 'Monster not found in this party');
        }

        $encounterMonster->delete();

        return response()->noContent();
    }

    /**
     * Clear all monsters from the party's encounter.
     *
     * TODO: Re-add ownership check when auth is implemented.
     */
    public function clear(Party $party): Response
    {
        $party->encounterMonsters()->delete();

        return response()->noContent();
    }

    /**
     * Get the highest label number for a monster type in this party.
     *
     * Note: Custom renamed labels (e.g., "Goblin Boss") won't affect auto-numbering.
     * Race condition possible with concurrent requests - duplicate labels may occur
     * but can be renamed by the DM.
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
