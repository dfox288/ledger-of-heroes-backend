<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DamageTypeIndexRequest;
use App\Http\Resources\DamageTypeResource;
use App\Http\Resources\ItemResource;
use App\Http\Resources\SpellResource;
use App\Models\DamageType;

class DamageTypeController extends Controller
{
    /**
     * List all damage types
     *
     * Returns a paginated list of D&D 5e damage types (Fire, Cold, Poison, Slashing, etc.).
     * Used for spell effects, weapon damage, and resistances/immunities.
     */
    public function index(DamageTypeIndexRequest $request)
    {
        $query = DamageType::query();

        // Add search support
        if ($request->has('q')) {
            $search = $request->validated('q');
            $query->where('name', 'LIKE', "%{$search}%");
        }

        // Add pagination support
        $perPage = $request->validated('per_page', 50); // Higher default for lookups
        $entities = $query->paginate($perPage);

        return DamageTypeResource::collection($entities);
    }

    /**
     * Get a single damage type
     *
     * Returns detailed information about a specific D&D damage type including its name
     * and associated spells, weapons, or effects.
     */
    public function show(DamageType $damageType)
    {
        return new DamageTypeResource($damageType);
    }

    /**
     * List all spells that deal this damage type
     *
     * Returns a paginated list of spells that deal this type of damage through their
     * primary or secondary effects. Useful for building themed characters (fire mage,
     * frost wizard) or finding spells to exploit enemy vulnerabilities.
     *
     * **Basic Examples:**
     * - Fire spells: `GET /api/v1/damage-types/fire/spells`
     * - Fire by ID: `GET /api/v1/damage-types/1/spells`
     * - Pagination: `GET /api/v1/damage-types/fire/spells?per_page=25&page=2`
     *
     * **Damage Type Use Cases:**
     * - Fire: Fireball, Burning Hands, Scorching Ray, Flame Strike (~24 spells)
     * - Cold: Ice Storm, Cone of Cold, Ray of Frost (~18 spells)
     * - Lightning: Lightning Bolt, Call Lightning, Chain Lightning (~12 spells)
     * - Psychic: Mind Spike, Synaptic Static, Psychic Scream (~15 spells)
     * - Necrotic: Blight, Vampiric Touch, Circle of Death (~20 spells)
     * - Radiant: Guiding Bolt, Sunbeam, Dawn, Sacred Flame (~16 spells)
     * - Thunder: Thunderwave, Shatter, Booming Blade (~10 spells)
     * - Poison: Poison Spray, Cloudkill, Stinking Cloud (~8 spells)
     * - Acid: Acid Splash, Vitriolic Sphere, Acid Arrow (~7 spells)
     * - Force: Magic Missile, Eldritch Blast, Disintegrate (~12 spells)
     *
     * **Character Building:**
     * - Elemental specialist builds (fire/cold/lightning mages)
     * - Exploit enemy vulnerabilities (undead vulnerable to radiant)
     * - Avoid resistances (many devils resist fire, use cold/lightning instead)
     * - Thematic spell selection (necromancer uses necrotic, cleric uses radiant)
     *
     * **Combat Tactics:**
     * - Identify damage type distribution in your spell list
     * - Prepare diverse damage types to handle resistances
     * - Focus on force/psychic for guaranteed damage (few resistances)
     *
     * **Reference Data:**
     * - 13 damage types in D&D 5e
     * - Most common: Fire (~24 spells), Necrotic (~20 spells), Cold (~18 spells)
     * - Least resisted: Force, Psychic, Radiant (best for reliable damage)
     * - Most resisted: Fire, Poison (many creatures have resistance/immunity)
     *
     * @param DamageType $damageType The damage type (by ID, code, or name)
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function spells(DamageType $damageType)
    {
        $perPage = request()->input('per_page', 50);

        $spells = $damageType->spells()
            ->with(['spellSchool', 'sources', 'tags'])
            ->orderBy('name')
            ->paginate($perPage);

        return SpellResource::collection($spells);
    }

    /**
     * List all items that deal this damage type
     *
     * Returns a paginated list of items (weapons, ammunition) that deal this type of damage.
     *
     * @param DamageType $damageType The damage type (by ID, code, or name)
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function items(DamageType $damageType)
    {
        $perPage = request()->input('per_page', 50);

        $items = $damageType->items()
            ->with(['itemType', 'sources', 'tags'])
            ->orderBy('name')
            ->paginate($perPage);

        return ItemResource::collection($items);
    }
}
