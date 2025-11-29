<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SpellSchoolIndexRequest;
use App\Http\Resources\SpellResource;
use App\Http\Resources\SpellSchoolResource;
use App\Models\SpellSchool;
use App\Services\Cache\LookupCacheService;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;

class SpellSchoolController extends Controller
{
    /**
     * List all schools of magic
     *
     * Returns the 8 schools of magic in D&D 5e. Every spell belongs to exactly one school,
     * which defines its magical nature and affects class features like Wizard school specialization.
     *
     * **Examples:**
     * ```
     * GET /api/v1/lookups/spell-schools              # All 8 schools
     * GET /api/v1/lookups/spell-schools?q=evocation  # Search by name
     * ```
     *
     * **The 8 Schools of Magic:**
     * - **Abjuration (AB):** Protective magic - Shield, Counterspell, Dispel Magic
     * - **Conjuration (C):** Summoning and teleportation - Misty Step, Conjure Animals
     * - **Divination (D):** Information gathering - Detect Magic, Identify, Scrying
     * - **Enchantment (EN):** Mind-affecting - Charm Person, Hold Person, Dominate
     * - **Evocation (EV):** Damage and energy - Fireball, Lightning Bolt, Magic Missile
     * - **Illusion (I):** Deception and trickery - Invisibility, Mirror Image, Major Image
     * - **Necromancy (N):** Life and death - Animate Dead, Vampiric Touch, Raise Dead
     * - **Transmutation (T):** Transformation - Polymorph, Haste, Enlarge/Reduce
     *
     * **Query Parameters:**
     * - `q` (string): Search schools by name (partial match)
     * - `per_page` (int): Results per page, 1-100 (default: 50)
     *
     * **Use Cases:**
     * - **Wizard Specialization:** Choose a school to gain bonus features (Evocation for damage, Divination for utility)
     * - **Spell Selection:** Browse spells by school to build a thematic caster
     * - **Counterspell Decisions:** Identify spell schools to prioritize countering
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    #[QueryParameter('q', description: 'Search schools by name', example: 'evocation')]
    public function index(SpellSchoolIndexRequest $request, LookupCacheService $cache)
    {
        $query = SpellSchool::query();

        // Search by name
        if ($request->has('q')) {
            $search = $request->validated('q');
            $query->where('name', 'LIKE', "%{$search}%");
        }

        // Pagination
        $perPage = $request->validated('per_page', 50);

        // Use cache for unfiltered queries, otherwise hit database
        if (! $request->has('q')) {
            // Manually paginate the cached collection to maintain API contract
            $allSchools = $cache->getSpellSchools();
            $currentPage = $request->input('page', 1);
            $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
                $allSchools->forPage($currentPage, $perPage),
                $allSchools->count(),
                $perPage,
                $currentPage,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            return SpellSchoolResource::collection($paginated);
        }

        return SpellSchoolResource::collection(
            $query->paginate($perPage)
        );
    }

    /**
     * Get a single school of magic
     *
     * Returns detailed information about a specific school of magic.
     * Schools can be retrieved by ID, code, slug, or name.
     *
     * **Examples:**
     * ```
     * GET /api/v1/lookups/spell-schools/3           # By ID
     * GET /api/v1/lookups/spell-schools/EV          # By code
     * GET /api/v1/lookups/spell-schools/evocation   # By slug
     * GET /api/v1/lookups/spell-schools/Evocation   # By name
     * ```
     *
     * **Response includes:**
     * - `id`, `code`, `name`, `slug`: School identification
     * - `description`: What this school of magic represents
     *
     * **Related endpoint:** Use `/api/v1/lookups/spell-schools/{id}/spells` to list all spells in this school.
     */
    public function show(SpellSchool $spellSchool)
    {
        return new SpellSchoolResource($spellSchool);
    }

    /**
     * List all spells in this school of magic
     *
     * Returns a paginated list of spells belonging to a specific school of magic.
     * Supports all spell fields including level, concentration, ritual, damage types,
     * saving throws, and component requirements.
     *
     * **Basic Examples:**
     * - Evocation spells: `GET /api/v1/spell-schools/evocation/spells`
     * - Evocation by ID: `GET /api/v1/spell-schools/3/spells`
     * - Evocation by code: `GET /api/v1/spell-schools/EV/spells`
     * - Pagination: `GET /api/v1/spell-schools/evocation/spells?per_page=25&page=2`
     *
     * **School-Specific Use Cases:**
     * - Damage dealers (Evocation): Direct damage spells (Fireball, Magic Missile, Lightning Bolt)
     * - Mind control (Enchantment): Charm Person, Dominate Monster, Suggestion
     * - Buffs & debuffs (Transmutation): Haste, Slow, Polymorph, Enlarge/Reduce
     * - Information gathering (Divination): Detect Magic, Scrying, Identify
     * - Defense (Abjuration): Shield, Counterspell, Dispel Magic, Protection spells
     * - Summoning (Conjuration): Summon spells, Create Food and Water, Teleport
     * - Trickery (Illusion): Invisibility, Mirror Image, Silent Image, Disguise Self
     * - Undead & life force (Necromancy): Animate Dead, Vampiric Touch, Speak with Dead
     *
     * **Character Building:**
     * - Wizard school specialization (pick one school to focus on)
     * - Spell selection optimization (identify your school's best spells)
     * - Thematic spellcasting (pure Evocation blaster, pure Enchantment controller)
     *
     * **Reference Data:**
     * - 8 schools of magic in D&D 5e
     * - Total: 477 spells across all schools
     * - Evocation: ~60 spells (largest school, damage-focused)
     * - Enchantment: ~40 spells (mind-affecting)
     * - Transmutation: ~55 spells (versatile utility)
     * - Conjuration: ~45 spells (summoning & teleportation)
     *
     * @param  SpellSchool  $spellSchool  The school of magic (by ID, code, or slug)
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function spells(Request $request, SpellSchool $spellSchool)
    {
        $perPage = $request->input('per_page', 50);

        $spells = $spellSchool->spells()
            ->with(['spellSchool', 'sources', 'tags'])
            ->paginate($perPage);

        return SpellResource::collection($spells);
    }
}
