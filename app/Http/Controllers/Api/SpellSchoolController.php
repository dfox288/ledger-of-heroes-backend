<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SpellSchoolIndexRequest;
use App\Http\Resources\SpellResource;
use App\Http\Resources\SpellSchoolResource;
use App\Models\SpellSchool;
use Illuminate\Http\Request;

class SpellSchoolController extends Controller
{
    /**
     * List all schools of magic
     *
     * Returns a paginated list of the 8 schools of magic in D&D 5e (Abjuration, Conjuration,
     * Divination, Enchantment, Evocation, Illusion, Necromancy, Transmutation).
     */
    public function index(SpellSchoolIndexRequest $request)
    {
        $query = SpellSchool::query();

        // Search by name
        if ($request->has('q')) {
            $search = $request->validated('q');
            $query->where('name', 'LIKE', "%{$search}%");
        }

        // Pagination
        $perPage = $request->validated('per_page', 50);

        return SpellSchoolResource::collection(
            $query->paginate($perPage)
        );
    }

    /**
     * Get a single school of magic
     *
     * Returns detailed information about a specific school of magic including its name
     * and associated spells.
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
