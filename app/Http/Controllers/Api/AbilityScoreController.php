<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AbilityScoreIndexRequest;
use App\Http\Resources\AbilityScoreResource;
use App\Http\Resources\SpellResource;
use App\Models\AbilityScore;
use App\Services\Cache\LookupCacheService;

class AbilityScoreController extends Controller
{
    /**
     * List all ability scores
     *
     * Returns a paginated list of the 6 core ability scores in D&D 5e (Strength, Dexterity,
     * Constitution, Intelligence, Wisdom, Charisma). Supports searching by name or code (e.g., "STR", "DEX").
     */
    public function index(AbilityScoreIndexRequest $request, LookupCacheService $cache)
    {
        $query = AbilityScore::query();

        // Search by name OR code
        if ($request->has('q')) {
            $search = $request->validated('q');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('code', 'LIKE', "%{$search}%");
            });
        }

        // Pagination
        $perPage = $request->validated('per_page', 50);

        // Use cache for unfiltered queries
        if (! $request->has('q')) {
            $allAbilityScores = $cache->getAbilityScores();
            $currentPage = $request->input('page', 1);
            $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
                $allAbilityScores->forPage($currentPage, $perPage),
                $allAbilityScores->count(),
                $perPage,
                $currentPage,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            return AbilityScoreResource::collection($paginated);
        }

        return AbilityScoreResource::collection(
            $query->paginate($perPage)
        );
    }

    /**
     * Get a single ability score
     *
     * Returns detailed information about a specific ability score including its full name,
     * code abbreviation, and associated skills.
     */
    public function show(AbilityScore $abilityScore)
    {
        return new AbilityScoreResource($abilityScore);
    }

    /**
     * List all spells that require this ability score for saving throws
     *
     * Returns a paginated list of spells that require saving throws using this ability score.
     * Spells are ordered alphabetically by name and include relationships (spell school,
     * sources, tags). Useful for building spell repertoires, targeting enemy weaknesses,
     * and understanding spell distribution across saving throw types.
     *
     * **Basic Examples:**
     * - Dexterity saves: `GET /api/v1/ability-scores/DEX/spells` (Fireball, Lightning Bolt)
     * - Wisdom saves: `GET /api/v1/ability-scores/WIS/spells` (Charm Person, Hold Person)
     * - Constitution saves: `GET /api/v1/ability-scores/CON/spells` (Cloudkill, Stinking Cloud)
     * - By ID: `GET /api/v1/ability-scores/2/spells`
     * - By name: `GET /api/v1/ability-scores/dexterity/spells`
     * - Pagination: `GET /api/v1/ability-scores/STR/spells?per_page=25`
     *
     * **Common Save Distribution (Approximate):**
     * - Dexterity (DEX): ~80 spells - Area damage (Fireball, Lightning Bolt), traps, explosions
     * - Wisdom (WIS): ~60 spells - Mental effects (Charm Person, Fear, Hold Person), illusions
     * - Constitution (CON): ~50 spells - Poison (Cloudkill), disease, exhaustion, concentration breaks
     * - Intelligence (INT): ~15 spells - Psychic damage (Phantasmal Force), mental traps, mind control
     * - Charisma (CHA): ~20 spells - Banishment, extraplanar effects (Banishment, Dispel Evil)
     * - Strength (STR): ~25 spells - Physical restraint (Entangle, Web), grappling, forced movement
     *
     * **Targeting Enemy Weaknesses:**
     * - **Wizards/Sorcerers**: Low STR/CON - Use Entangle, Web, poison spells
     * - **Fighters/Barbarians**: Low INT/WIS/CHA - Use charm, fear, banishment spells
     * - **Rogues**: Low STR/WIS - Use grappling, charm, or fear effects
     * - **Clerics/Druids**: Low DEX/INT - Use area damage (Fireball), psychic attacks
     * - **Beasts/Constructs**: Low INT/CHA - Use mind-affecting spells (often immune, check first!)
     * - **Undead**: Low CHA (usually) - Varies widely, check individual monster stats
     *
     * **Save Effect Types:**
     * - **Negates**: Save completely negates spell effect (Charm Person, Hold Person)
     * - **Half Damage**: Save reduces damage by half (Fireball, Lightning Bolt, most evocation)
     * - **Ends Effect**: Save ends ongoing effect (Fear - save at end of each turn)
     * - **Reduced Duration**: Save shortens spell duration
     *
     * **Building Save-Focused Characters:**
     * - **Evocation Wizard**: Focus on DEX saves (Sculpt Spells lets allies auto-succeed)
     * - **Enchantment Wizard**: Focus on WIS/CHA saves, boost DC with features
     * - **Control Wizard**: Mix STR/DEX/WIS saves to target multiple weaknesses
     * - **Debuff Cleric**: CON/WIS saves for poison, disease, mental effects
     * - **Spell Selection**: Cover 3+ save types to handle different enemy stat arrays
     *
     * **Spell DC Optimization:**
     * - Base DC = 8 + proficiency bonus + spellcasting ability modifier
     * - Boost DC: +1 items (Rod of the Pact Keeper), class features, spells (Bestow Curse)
     * - Average DC by level: 13 (level 1), 15 (level 5), 17 (level 11), 19 (level 17)
     * - Enemy saves scale slower than DC - advantage grows at higher levels
     *
     * **Tactical Considerations:**
     * - **Action Economy**: Save-or-suck spells (Hold Person) can eliminate threats instantly
     * - **Concentration**: Many save spells require concentration - protect it!
     * - **Legendary Resistance**: High-CR enemies can auto-succeed 3 times - burn through them
     * - **Magic Resistance**: Some enemies have advantage on saves vs spells - still worth targeting weak saves
     * - **Repeated Saves**: Some spells allow saves each round (Fear) - less reliable but safer
     *
     * **Reference Data:**
     * - 6 ability scores in D&D 5e (STR, DEX, CON, INT, WIS, CHA)
     * - ~250+ total spells require saving throws (~50% of all spells)
     * - Most common: DEX (~80 spells), WIS (~60 spells), CON (~50 spells)
     * - Least common: INT (~15 spells) - exploit this vs low-INT enemies!
     * - Save DCs range from 13 (level 1) to 19+ (level 17+)
     *
     * @param  AbilityScore  $abilityScore  The ability score (by ID, code, or name)
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function spells(AbilityScore $abilityScore)
    {
        $perPage = request()->input('per_page', 50);

        $spells = $abilityScore->spells()
            ->with(['spellSchool', 'sources', 'tags'])
            ->orderBy('name')
            ->paginate($perPage);

        return SpellResource::collection($spells);
    }
}
