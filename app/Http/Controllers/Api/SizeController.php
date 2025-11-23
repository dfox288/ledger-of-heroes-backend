<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SizeIndexRequest;
use App\Http\Resources\MonsterResource;
use App\Http\Resources\RaceResource;
use App\Http\Resources\SizeResource;
use App\Models\Size;
use App\Services\Cache\LookupCacheService;
use Illuminate\Http\Request;

class SizeController extends Controller
{
    /**
     * List all creature sizes
     *
     * Returns a paginated list of D&D 5e creature sizes (Tiny, Small, Medium, Large, Huge, Gargantuan).
     * Used to categorize creatures, races, and determine space occupied in combat.
     */
    public function index(SizeIndexRequest $request, LookupCacheService $cache)
    {
        $query = Size::query();

        // Search by name
        if ($request->has('q')) {
            $search = $request->validated('q');
            $query->where('name', 'LIKE', "%{$search}%");
        }

        // Pagination
        $perPage = $request->validated('per_page', 50);

        // Use cache for unfiltered queries
        if (! $request->has('q')) {
            $allSizes = $cache->getSizes();
            $currentPage = $request->input('page', 1);
            $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
                $allSizes->forPage($currentPage, $perPage),
                $allSizes->count(),
                $perPage,
                $currentPage,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            return SizeResource::collection($paginated);
        }

        return SizeResource::collection(
            $query->paginate($perPage)
        );
    }

    /**
     * Get a single size category
     *
     * Returns detailed information about a specific creature size including its space
     * requirements and rules implications.
     */
    public function show(Size $size)
    {
        return new SizeResource($size);
    }

    /**
     * List all races of this size category
     *
     * Returns a paginated list of playable and monster races that are of the specified size.
     * Size affects combat mechanics, grappling rules, mounted combat, and dungeon navigation.
     *
     * **Examples:**
     * ```bash
     * # By code (T=Tiny, S=Small, M=Medium, L=Large, H=Huge, G=Gargantuan)
     * GET /api/v1/sizes/S/races      # Small races
     * GET /api/v1/sizes/M/races      # Medium races
     * GET /api/v1/sizes/T/races?per_page=25
     * ```
     *
     * **Size Categories & Common Races:**
     * - **Tiny (1):** No playable races, some monster templates
     * - **Small (2):** Halfling, Gnome, Kobold, Goblin (~22 races total)
     *   - Popular for Rogue/Ranger builds (stealth bonus)
     *   - Can ride Medium creatures as mounts
     *   - Disadvantage with Heavy weapons
     * - **Medium (3):** Human, Elf, Dwarf, Half-Elf, Tiefling, most races (~93 races total)
     *   - Standard size for most player characters
     *   - No size-based combat restrictions
     *   - Can be mounts for Small creatures
     * - **Large (4):** Centaur, Minotaur (rare playable races)
     *   - Powerful melee builds
     *   - Cannot fit through standard doors (5ft width)
     *   - Can grapple Huge creatures
     * - **Huge (5) & Gargantuan (6):** Monster-only sizes (no playable races)
     *
     * **Use Cases:**
     * - **Race selection:** "I want to play a Small character for stealth and mounted combat builds"
     * - **Mechanical planning:** Small races can ride Medium creatures (find a wolf companion!)
     * - **Grappling optimization:** Can only grapple targets within one size category of you
     * - **Dungeon design awareness:** Small races fit through 2.5ft spaces, Large+ struggle with doors
     * - **Party composition:** Ensure size diversity for tactical advantages
     *
     * **Combat Mechanics by Size:**
     * - **Small creatures:**
     *   - Disadvantage on attack rolls with Heavy weapons (greatsword, maul)
     *   - Can move through spaces of Medium or larger creatures (squeeze rules)
     *   - Can ride Medium creatures as mounts (mounted combat synergy)
     *   - Take up 5ft × 5ft space (same as Medium)
     *   - Can fit through 2.5ft wide spaces without squeezing
     * - **Medium creatures:**
     *   - No size-based restrictions (standard baseline)
     *   - Can grapple Small to Large creatures
     *   - Can serve as mounts for Small creatures
     *   - Take up 5ft × 5ft space
     * - **Large+ creatures:**
     *   - Take up 10ft × 10ft space (Large), 15ft × 15ft (Huge), 20ft × 20ft (Gargantuan)
     *   - Reach weapons extend control zones
     *   - Cannot fit through standard 5ft doorways
     *   - Powerful grappling potential (can grapple Huge creatures as Large)
     *
     * **Grappling Rules by Size:**
     * - Can only grapple creatures within **one size category** of you
     * - Small creature can grapple: Tiny or Small
     * - Medium creature can grapple: Small, Medium, or Large
     * - Large creature can grapple: Medium, Large, or Huge
     * - **Use case:** "Which Small races can I play if I want to grapple Small enemies?"
     *
     * **Mounted Combat Rules:**
     * - Mount must be **one size category larger** than rider
     * - Small creatures can ride Medium mounts (ponies, wolves, mastiffs)
     * - Medium creatures can ride Large mounts (horses, camels, elk)
     * - **Use case:** "Which Small races can I play for a mounted Rogue build?"
     *
     * **Space & Movement:**
     * - Small/Medium: 5ft × 5ft space (1 square)
     * - Large: 10ft × 10ft space (4 squares)
     * - Huge: 15ft × 15ft space (9 squares)
     * - Gargantuan: 20ft × 20ft space (16 squares)
     * - **Squeezing:** Can squeeze through spaces half your width at half speed (Small through 1.25ft)
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function races(Request $request, Size $size)
    {
        $perPage = $request->input('per_page', 50);

        $races = $size->races()
            ->with(['size', 'sources', 'tags'])
            ->paginate($perPage);

        return RaceResource::collection($races);
    }

    /**
     * List all monsters of this size category
     *
     * Returns a paginated list of monsters that are of the specified size.
     * Size affects encounter balance, tactics, space control, and environmental challenges.
     *
     * **Examples:**
     * ```bash
     * # By code (T=Tiny, S=Small, M=Medium, L=Large, H=Huge, G=Gargantuan)
     * GET /api/v1/sizes/T/monsters     # Tiny monsters
     * GET /api/v1/sizes/L/monsters     # Large monsters
     * GET /api/v1/sizes/H/monsters?per_page=25  # Huge monsters
     * ```
     *
     * **Size Categories & Common Monsters:**
     * - **Tiny (1):** Sprites, Imps, Flying Snakes, Cranium Rats (~55 monsters)
     *   - Often swarms or scouts
     *   - High stealth potential
     *   - Low HP, used in large groups
     *   - CR range: 0-4 (some exceptions)
     * - **Small (2):** Goblins, Kobolds, Pixies, Stirges (~49 monsters)
     *   - Common low-level threats
     *   - Pack tactics frequent
     *   - CR range: 0-8
     * - **Medium (3):** Humans, Orcs, Zombies, Bugbears, most humanoids (~280 monsters)
     *   - Largest category (47% of all monsters)
     *   - CR range: 0-30 (Ancient Vampire)
     *   - Includes iconic threats like Beholders, Mind Flayers
     * - **Large (4):** Ogres, Ettins, Young Dragons, Owlbears (~151 monsters)
     *   - Serious combat threats
     *   - 10ft reach common
     *   - CR range: 1-22 (Adult Dragons)
     * - **Huge (5):** Giants, Adult Dragons, Purple Worms (~47 monsters)
     *   - Boss-tier enemies
     *   - Space control dominance
     *   - CR range: 4-24 (Ancient Dragons)
     * - **Gargantuan (6):** Ancient Dragons, Krakens, Tarrasque (~16 monsters)
     *   - Legendary encounters
     *   - World-ending threats
     *   - CR range: 10-30 (Tarrasque)
     *
     * **Use Cases:**
     * - **Encounter building:** "I need Large creatures for a CR 5 encounter in a 30ft × 30ft room"
     * - **Tactical planning:** "Which Huge monsters can't fit through this 10ft tunnel?" (All of them!)
     * - **Boss selection:** "Give me all Gargantuan monsters for a campaign finale" (16 options)
     * - **Environmental challenges:** "Which monsters can squeeze through tight spaces?" (Tiny/Small)
     * - **Space control:** "Which Large monsters have 10ft reach?" (filter by size, check actions)
     *
     * **Combat Tactics by Monster Size:**
     * - **Tiny/Small monsters:**
     *   - Use in swarms (5-10 creatures)
     *   - Focus on mobility and stealth
     *   - Gang up for Pack Tactics advantage
     *   - Easy to hit (low AC typical) but numerous
     * - **Medium monsters:**
     *   - Standard 5ft reach, 5ft × 5ft space
     *   - Most versatile size for varied tactics
     *   - Can navigate any terrain
     * - **Large monsters:**
     *   - Often 10ft reach (control 24 squares!)
     *   - Block doorways and corridors
     *   - 4× space of Medium (tactical positioning critical)
     *   - Grapple up to Huge creatures
     * - **Huge monsters:**
     *   - 15ft reach possible (control 48 squares!)
     *   - Dominate battlefield positioning
     *   - Cannot fit through 10ft doors
     *   - Legendary boss-tier threats
     * - **Gargantuan monsters:**
     *   - 20ft reach typical (control 80+ squares!)
     *   - Outdoor-only encounters (can't fit in dungeons)
     *   - Siege-scale threats
     *   - Legendary Actions almost guaranteed
     *
     * **Encounter Design Considerations:**
     * - **Space required:** Large needs 10ft × 10ft, Huge needs 15ft × 15ft, Gargantuan needs 20ft × 20ft
     * - **Reach zones:** Large creatures with 10ft reach control massive areas (don't bunch up!)
     * - **Terrain challenges:** Huge+ creatures can't fit through standard dungeon corridors (10ft wide)
     * - **Grappling threat:** Large+ monsters can grapple and restrain Medium PCs easily
     * - **Action economy:** Use multiple Small/Medium monsters vs single Large+ for balance
     *
     * **CR Distribution by Size:**
     * - Tiny: Mostly CR 0-4 (swarm fodder, scouts)
     * - Small: CR 0-8 (goblinoids, common threats)
     * - Medium: CR 0-30 (full range, most versatile)
     * - Large: CR 1-22 (mid-tier bosses, dragons)
     * - Huge: CR 4-24 (high-tier bosses, ancient dragons)
     * - Gargantuan: CR 10-30 (legendary encounters only)
     *
     * **Environmental Constraints:**
     * - **Standard door:** 5ft wide (Large+ squeeze, Huge+ cannot fit)
     * - **Wide corridor:** 10ft wide (Huge+ squeeze, Gargantuan cannot fit)
     * - **Cavern/outdoor:** Only limitation is Gargantuan (20ft × 20ft minimum)
     * - **Flying creatures:** Size matters less (can maneuver in 3D space)
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function monsters(Request $request, Size $size)
    {
        $perPage = $request->input('per_page', 50);

        $monsters = $size->monsters()
            ->with(['size', 'sources'])
            ->paginate($perPage);

        return MonsterResource::collection($monsters);
    }
}
