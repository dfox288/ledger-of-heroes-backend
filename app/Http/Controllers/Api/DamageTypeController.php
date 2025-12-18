<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DamageTypeIndexRequest;
use App\Http\Resources\DamageTypeResource;
use App\Http\Resources\ItemResource;
use App\Http\Resources\SpellResource;
use App\Models\DamageType;
use App\Services\Cache\LookupCacheService;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

class DamageTypeController extends Controller
{
    /**
     * List all damage types
     *
     * Returns the 13 D&D 5e damage types used for spell effects, weapon damage, and creature resistances/immunities.
     * Damage types are divided into physical (Slashing, Piercing, Bludgeoning) and elemental/energy types.
     *
     * **Examples:**
     * ```
     * GET /api/v1/lookups/damage-types              # All 13 damage types
     * GET /api/v1/lookups/damage-types?q=fire       # Search by name
     * ```
     *
     * **Damage Types Reference:**
     * - **Physical (3):** Slashing, Piercing, Bludgeoning - from weapons, most common
     * - **Elemental (4):** Fire, Cold, Lightning, Acid - natural elements
     * - **Energy (3):** Force, Radiant, Necrotic - magical energy
     * - **Other (3):** Poison, Psychic, Thunder - special types
     *
     * **Query Parameters:**
     * - `q` (string): Search by name (partial match)
     * - `per_page` (int): Results per page, 1-100 (default: 50)
     *
     * **Use Cases:**
     * - **Resistance Planning:** Check enemy resistances before combat (many undead resist necrotic)
     * - **Spell Selection:** Build diverse spell lists with multiple damage types
     * - **Vulnerability Exploitation:** Target known vulnerabilities (trolls vs. fire/acid)
     *
     * @response AnonymousResourceCollection<DamageTypeResource>
     */
    #[QueryParameter('q', description: 'Search damage types by name', example: 'fire')]
    public function index(DamageTypeIndexRequest $request, LookupCacheService $cache): AnonymousResourceCollection
    {
        $query = DamageType::query();

        // Search by name
        if ($request->has('q')) {
            $search = $request->validated('q');
            $query->where('name', 'LIKE', "%{$search}%");
        }

        $perPage = $request->validated('per_page', 50);

        // Use cache for unfiltered queries
        if (! $request->has('q')) {
            $allRecords = $cache->getDamageTypes();
            $currentPage = $request->input('page', 1);
            $paginated = new LengthAwarePaginator(
                $allRecords->forPage($currentPage, $perPage),
                $allRecords->count(),
                $perPage,
                $currentPage,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            return DamageTypeResource::collection($paginated);
        }

        return DamageTypeResource::collection($query->paginate($perPage));
    }

    /**
     * Get a single damage type
     *
     * Returns detailed information about a specific D&D damage type including its name and code.
     * Damage types can be retrieved by ID, slug, code, or name.
     *
     * **Examples:**
     * ```
     * GET /api/v1/lookups/damage-types/1              # By ID
     * GET /api/v1/lookups/damage-types/fire           # By slug
     * GET /api/v1/lookups/damage-types/F              # By code
     * GET /api/v1/lookups/damage-types/Fire           # By name
     * ```
     *
     * **Response includes:**
     * - `id`, `name`, `slug`: Damage type identification
     * - `code`: Single-letter abbreviation (F=Fire, C=Cold, L=Lightning, etc.)
     */
    public function show(DamageType $damageType): DamageTypeResource
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
     * - Fire by code: `GET /api/v1/damage-types/F/spells`
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
     * @param  DamageType  $damageType  The damage type (by ID, code, or name)
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
     * Returns a paginated list of weapons, ammunition, and magic items that deal
     * this type of damage. Useful for optimizing weapon selection and finding items
     * that exploit enemy vulnerabilities.
     *
     * **Basic Examples:**
     * - Slashing weapons: `GET /api/v1/damage-types/slashing/items`
     * - Fire items: `GET /api/v1/damage-types/fire/items`
     * - Slashing by code: `GET /api/v1/damage-types/S/items`
     * - Pagination: `GET /api/v1/damage-types/slashing/items?per_page=50`
     *
     * **Physical Damage Types (Weapons):**
     * - Slashing: Longsword, Greatsword, Scimitar, Battleaxe (~80 items)
     * - Piercing: Rapier, Longbow, Shortbow, Dagger, Pike (~70 items)
     * - Bludgeoning: Mace, Warhammer, Club, Quarterstaff, Maul (~60 items)
     *
     * **Elemental Damage Types (Magic Items):**
     * - Fire: Flame Tongue, Fire Arrow, Javelin of Lightning (~12 items)
     * - Cold: Frost Brand, Arrows of Ice Slaying (~5 items)
     * - Lightning: Javelin of Lightning, Lightning Arrow (~4 items)
     * - Poison: Serpent Venom (poison), Poison Dagger (~6 items)
     * - Acid: Acid Vial, Acid Arrow (~3 items)
     *
     * **Character Building:**
     * - Martial characters: Identify all weapons matching your proficiencies
     * - Damage optimization: Find magic weapons with bonus elemental damage
     * - Versatility: Carry multiple damage types to bypass resistances
     * - Exploit vulnerabilities: Trolls regenerate except for fire/acid damage
     *
     * **Combat Tactics:**
     * - Physical damage: Most common, many creatures resist
     * - Magical slashing/piercing/bludgeoning: Bypass non-magical resistance
     * - Elemental damage: Exploit specific vulnerabilities (fire vs. ice creatures)
     *
     * **Reference Data:**
     * - 13 damage types total
     * - Physical types: Slashing (~80), Piercing (~70), Bludgeoning (~60)
     * - Elemental types: Fire (~12), Poison (~6), Cold (~5), Lightning (~4)
     * - Magic weapons override resistances to non-magical damage
     *
     * @param  DamageType  $damageType  The damage type (by ID, code, or name)
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
