<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Party\EncounterPresetStoreRequest;
use App\Http\Requests\Party\EncounterPresetUpdateRequest;
use App\Http\Resources\EncounterMonsterResource;
use App\Http\Resources\EncounterPresetResource;
use App\Models\EncounterMonster;
use App\Models\EncounterPreset;
use App\Models\Monster;
use App\Models\Party;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PartyEncounterPresetController extends Controller
{
    /**
     * List all presets for the party.
     */
    public function index(Party $party): AnonymousResourceCollection
    {
        $presets = $party->encounterPresets()->with('monsters')->get();

        return EncounterPresetResource::collection($presets);
    }

    /**
     * Create a new encounter preset.
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
     */
    public function load(Party $party, EncounterPreset $encounterPreset): JsonResponse
    {
        if ($encounterPreset->party_id !== $party->id) {
            abort(404, 'Preset not found in this party');
        }

        $encounterPreset->load('monsters');
        $createdIds = [];

        foreach ($encounterPreset->monsters as $monster) {
            $quantity = $monster->pivot->quantity;
            $highestNumber = $this->getHighestLabelNumber($party, $monster);

            for ($i = 1; $i <= $quantity; $i++) {
                $encounterMonster = $party->encounterMonsters()->create([
                    'monster_id' => $monster->id,
                    'label' => $monster->name.' '.($highestNumber + $i),
                    'current_hp' => $monster->hit_points_average,
                    'max_hp' => $monster->hit_points_average,
                ]);
                $createdIds[] = $encounterMonster->id;
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
