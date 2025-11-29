<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SkillIndexRequest;
use App\Http\Resources\SkillResource;
use App\Models\Skill;
use Dedoc\Scramble\Attributes\QueryParameter;

class SkillController extends Controller
{
    /**
     * List all D&D skills
     *
     * Returns the 18 D&D 5e skills, each linked to one of the six ability scores.
     * Skills represent trained abilities characters can become proficient in.
     *
     * **Examples:**
     * ```
     * GET /api/v1/lookups/skills                  # All 18 skills
     * GET /api/v1/lookups/skills?q=stealth        # Search by name
     * GET /api/v1/lookups/skills?ability=DEX      # Dexterity skills (Acrobatics, Sleight of Hand, Stealth)
     * GET /api/v1/lookups/skills?ability=WIS      # Wisdom skills (Animal Handling, Insight, Medicine, Perception, Survival)
     * GET /api/v1/lookups/skills?ability=CHA      # Charisma skills (Deception, Intimidation, Performance, Persuasion)
     * ```
     *
     * **Skills by Ability Score:**
     * - **STR (1):** Athletics
     * - **DEX (3):** Acrobatics, Sleight of Hand, Stealth
     * - **INT (5):** Arcana, History, Investigation, Nature, Religion
     * - **WIS (5):** Animal Handling, Insight, Medicine, Perception, Survival
     * - **CHA (4):** Deception, Intimidation, Performance, Persuasion
     * - **CON (0):** No skills (Constitution has no associated skills)
     *
     * **Query Parameters:**
     * - `q` (string): Search skills by name (partial match)
     * - `ability` (string): Filter by ability score code (STR, DEX, CON, INT, WIS, CHA)
     * - `per_page` (int): Results per page, 1-100 (default: 50)
     *
     * **Use Cases:**
     * - **Character Building:** Find which skills use your highest ability score
     * - **Party Composition:** Ensure skill coverage across the party (Perception, Investigation, Stealth)
     * - **Class Selection:** Rogues excel at DEX skills, Clerics at WIS skills, Bards at CHA skills
     * - **Background Selection:** Backgrounds grant 2 skill proficiencies - pick ones matching your build
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    #[QueryParameter('q', description: 'Search skills by name', example: 'perception')]
    #[QueryParameter('ability', description: 'Filter by ability score code (STR, DEX, CON, INT, WIS, CHA)', example: 'DEX')]
    public function index(SkillIndexRequest $request)
    {
        $query = Skill::query()->with('abilityScore');

        // Search by name
        if ($request->has('q')) {
            $search = $request->validated('q');
            $query->where('name', 'LIKE', "%{$search}%");
        }

        // Filter by ability score code
        if ($request->has('ability')) {
            $query->whereHas('abilityScore', fn ($q) => $q->where('code', strtoupper($request->validated('ability')))
            );
        }

        // Pagination
        $perPage = $request->validated('per_page', 50);

        return SkillResource::collection(
            $query->paginate($perPage)
        );
    }

    /**
     * Get a single skill
     *
     * Returns detailed information about a specific D&D skill including its associated
     * ability score. Skills can be retrieved by ID, slug, or name.
     *
     * **Examples:**
     * ```
     * GET /api/v1/lookups/skills/1              # By ID
     * GET /api/v1/lookups/skills/perception     # By slug
     * GET /api/v1/lookups/skills/Perception     # By name
     * ```
     *
     * **Response includes:**
     * - `id`, `name`, `slug`: Skill identification
     * - `ability_score`: The governing ability (STR, DEX, CON, INT, WIS, CHA)
     */
    public function show(Skill $skill)
    {
        return new SkillResource($skill);
    }
}
