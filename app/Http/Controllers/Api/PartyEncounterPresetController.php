<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Party\EncounterPresetStoreRequest;
use App\Http\Requests\Party\EncounterPresetUpdateRequest;
use App\Http\Resources\EncounterMonsterResource;
use App\Http\Resources\EncounterPresetResource;
use App\Models\EncounterMonster;
use App\Models\EncounterPreset;
use App\Models\Party;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PartyEncounterPresetController extends Controller
{
    /**
     * List all presets for the party.
     *
     * TODO: Re-add ownership check when auth is implemented.
     */
    public function index(Party $party): AnonymousResourceCollection
    {
        $presets = $party->encounterPresets()->with('monsters')->get();

        return EncounterPresetResource::collection($presets);
    }

    /**
     * Create a new encounter preset.
     *
     * TODO: Re-add ownership check when auth is implemented.
     */
    public function store(EncounterPresetStoreRequest $request, Party $party): JsonResponse
    {
        $preset = $party->encounterPresets()->create([
            'name' => $request->validated('name'),
        ]);

        foreach ($request->validated('monsters') as $monsterData) {
            $preset->monsters()->attach($monsterData['monster_id'], [
                'quantity' => $monsterData['quantity'] ?? 1,
            ]);
        }

        $preset->load('monsters');

        return (new EncounterPresetResource($preset))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Update an encounter preset (rename).
     *
     * TODO: Re-add ownership check when auth is implemented.
     */
    public function update(EncounterPresetUpdateRequest $request, Party $party, EncounterPreset $encounterPreset): EncounterPresetResource
    {
        if ($encounterPreset->party_id !== $party->id) {
            abort(404, 'Preset not found in this party');
        }

        $encounterPreset->update($request->validated());
        $encounterPreset->load('monsters');

        return new EncounterPresetResource($encounterPreset);
    }

    /**
     * Delete an encounter preset.
     *
     * TODO: Re-add ownership check when auth is implemented.
     */
    public function destroy(Party $party, EncounterPreset $encounterPreset): JsonResponse
    {
        if ($encounterPreset->party_id !== $party->id) {
            abort(404, 'Preset not found in this party');
        }

        $encounterPreset->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Load a preset into the party's encounter.
     *
     * Creates EncounterMonster records for each monster in the preset.
     *
     * TODO: Re-add ownership check when auth is implemented.
     */
    public function load(Party $party, EncounterPreset $encounterPreset): JsonResponse
    {
        if ($encounterPreset->party_id !== $party->id) {
            abort(404, 'Preset not found in this party');
        }

        $encounterPreset->load('monsters');

        // Pre-fetch all existing labels to avoid N+1 queries
        $monsterIds = $encounterPreset->monsters->pluck('id')->all();
        $highestNumbers = $this->getHighestLabelNumbers($party, $monsterIds);

        $createdIds = [];

        foreach ($encounterPreset->monsters as $monster) {
            $quantity = $monster->pivot->quantity;
            $highestNumber = $highestNumbers[$monster->id] ?? 0;

            for ($i = 1; $i <= $quantity; $i++) {
                $encounterMonster = $party->encounterMonsters()->create([
                    'monster_id' => $monster->id,
                    'label' => $monster->name.' '.($highestNumber + $i),
                    'current_hp' => $monster->hit_points_average,
                    'max_hp' => $monster->hit_points_average,
                ]);
                $createdIds[] = $encounterMonster->id;
                // Update running count for subsequent monsters of same type
                $highestNumbers[$monster->id] = $highestNumber + $i;
            }
        }

        $created = EncounterMonster::whereIn('id', $createdIds)
            ->with(['monster.actions' => fn ($q) => $q->where('action_type', '!=', 'reaction')])
            ->get();

        return EncounterMonsterResource::collection($created)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Get the highest label numbers for multiple monster types in one query.
     *
     * @param  array<int>  $monsterIds
     * @return array<int, int> Map of monster_id => highest_number
     */
    private function getHighestLabelNumbers(Party $party, array $monsterIds): array
    {
        if (empty($monsterIds)) {
            return [];
        }

        $existingLabels = $party->encounterMonsters()
            ->whereIn('monster_id', $monsterIds)
            ->with('monster:id,name')
            ->get(['id', 'monster_id', 'label']);

        $highestNumbers = [];

        foreach ($existingLabels as $encounterMonster) {
            $monsterName = $encounterMonster->monster->name;
            $pattern = '/'.preg_quote($monsterName, '/').' (\d+)$/';

            if (preg_match($pattern, $encounterMonster->label, $matches)) {
                $number = (int) $matches[1];
                $monsterId = $encounterMonster->monster_id;

                if (! isset($highestNumbers[$monsterId]) || $number > $highestNumbers[$monsterId]) {
                    $highestNumbers[$monsterId] = $number;
                }
            }
        }

        return $highestNumbers;
    }
}
