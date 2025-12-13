<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Party\PartyAddCharacterRequest;
use App\Http\Requests\Party\PartyStoreRequest;
use App\Http\Requests\Party\PartyUpdateRequest;
use App\Http\Resources\PartyCharacterStatsResource;
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

    /**
     * Get aggregated stats for all characters in a party (DM dashboard).
     */
    public function stats(Request $request, Party $party): JsonResponse
    {
        // Check ownership
        if ($party->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Party not found'], Response::HTTP_NOT_FOUND);
        }

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
        ]);

        return response()->json([
            'data' => [
                'party' => [
                    'id' => $party->id,
                    'name' => $party->name,
                ],
                'characters' => PartyCharacterStatsResource::collection($party->characters),
                'party_summary' => $this->calculatePartySummary($party->characters),
            ],
        ]);
    }

    /**
     * Calculate party-wide summary aggregations for DM reference.
     */
    private function calculatePartySummary($characters): array
    {
        // Healer classes (can be expanded)
        $healerClasses = ['cleric', 'druid', 'paladin', 'bard'];

        // Utility spell base names to check for (matches any prefix like phb:, xge:, etc.)
        $utilitySpellNames = [
            'detect_magic' => 'detect-magic',
            'dispel_magic' => 'dispel-magic',
            'counterspell' => 'counterspell',
        ];

        // Aggregate all languages
        $allLanguages = $characters
            ->flatMap(fn ($char) => $char->languages->map(fn ($cl) => $cl->language?->name))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        // Darkvision tracking
        $darkvisionCount = 0;
        $noDarkvision = [];

        foreach ($characters as $character) {
            $hasDarkvision = false;

            if ($character->race && $character->race->relationLoaded('senses')) {
                $hasDarkvision = $character->race->senses->contains(function ($entitySense) {
                    return $entitySense->sense?->slug === 'core:darkvision';
                });
            }

            if ($hasDarkvision) {
                $darkvisionCount++;
            } else {
                $noDarkvision[] = $character->name;
            }
        }

        // Healer tracking
        $healers = [];
        foreach ($characters as $character) {
            $primaryClass = $character->characterClasses->firstWhere('is_primary', true)?->characterClass;
            if ($primaryClass) {
                $classSlug = $primaryClass->slug ?? '';
                $className = $primaryClass->name ?? '';

                // Check if class slug contains any healer class name
                foreach ($healerClasses as $healerClass) {
                    if (str_contains(strtolower($classSlug), $healerClass)) {
                        $healers[] = "{$character->name} ({$className})";
                        break;
                    }
                }
            }
        }

        // Utility spell tracking - check if any party spell ends with the base name
        $partySpellSlugs = $characters
            ->flatMap(fn ($char) => $char->spells->map(fn ($cs) => $cs->spell_slug))
            ->filter()
            ->unique()
            ->all();

        $hasSpell = fn (string $baseName) => collect($partySpellSlugs)
            ->contains(fn ($slug) => str_ends_with($slug, $baseName));

        $hasDetectMagic = $hasSpell($utilitySpellNames['detect_magic']);
        $hasDispelMagic = $hasSpell($utilitySpellNames['dispel_magic']);
        $hasCounterspell = $hasSpell($utilitySpellNames['counterspell']);

        return [
            'all_languages' => $allLanguages,
            'darkvision_count' => $darkvisionCount,
            'no_darkvision' => $noDarkvision,
            'has_healer' => count($healers) > 0,
            'healers' => $healers,
            'has_detect_magic' => $hasDetectMagic,
            'has_dispel_magic' => $hasDispelMagic,
            'has_counterspell' => $hasCounterspell,
        ];
    }
}
