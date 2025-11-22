<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConditionIndexRequest;
use App\Http\Resources\ConditionResource;
use App\Http\Resources\MonsterResource;
use App\Http\Resources\SpellResource;
use App\Models\Condition;

class ConditionController extends Controller
{
    /**
     * List all D&D conditions
     *
     * Returns a paginated list of D&D 5e conditions (Blinded, Charmed, Frightened, etc.).
     * These are status effects that can be applied to creatures during combat.
     */
    public function index(ConditionIndexRequest $request)
    {
        $query = Condition::query();

        // Add search support
        if ($request->has('q')) {
            $search = $request->validated('q');
            $query->where('name', 'LIKE', "%{$search}%");
        }

        // Add pagination support
        $perPage = $request->validated('per_page', 50); // Higher default for lookups
        $entities = $query->paginate($perPage);

        return ConditionResource::collection($entities);
    }

    /**
     * Get a single condition
     *
     * Returns detailed information about a specific D&D condition including its rules
     * and effects on gameplay.
     */
    public function show(Condition $condition)
    {
        return new ConditionResource($condition);
    }

    /**
     * List all spells that inflict this condition
     *
     * Returns a paginated list of spells that can inflict this condition on targets
     * through saving throw failures. Useful for building control-focused characters
     * and identifying debuff options.
     *
     * **Basic Examples:**
     * - Poison spells: `GET /api/v1/conditions/poisoned/spells`
     * - Stun spells: `GET /api/v1/conditions/stunned/spells`
     * - By ID: `GET /api/v1/conditions/5/spells`
     * - Pagination: `GET /api/v1/conditions/paralyzed/spells?per_page=25`
     *
     * **Common Condition Use Cases:**
     * - Poisoned: Poison Spray, Cloudkill, Contagion (~8 spells, CON save)
     * - Stunned: Power Word Stun, Shocking Grasp (high levels) (~4 spells, CON save)
     * - Paralyzed: Hold Person, Hold Monster (~6 spells, WIS save, auto-crit)
     * - Charmed: Charm Person, Dominate Monster, Suggestion (~12 spells, WIS save)
     * - Frightened: Cause Fear, Fear, Phantasmal Killer (~8 spells, WIS save)
     * - Restrained: Entangle, Web, Evard's Black Tentacles (~10 spells, STR/DEX save)
     * - Blinded: Blindness/Deafness, Sunburst (~6 spells, CON save)
     * - Deafened: Deafness, Thunder Step (~4 spells, CON save)
     * - Prone: Grease, Thunderwave (~8 spells, STR/DEX save)
     * - Invisible: Invisibility, Greater Invisibility (~6 spells, no save)
     *
     * **Control Wizard Builds:**
     * - Crowd control: Paralyzed (auto-crits), Stunned (no actions), Restrained (reduced movement)
     * - Debuffs: Poisoned (disadvantage on attacks), Frightened (can't approach)
     * - Social manipulation: Charmed (friendly, can't attack), Suggestion (follow command)
     *
     * **Combat Tactics:**
     * - High-value targets: Paralyze enemy spellcasters (no verbal components)
     * - Melee threats: Restrain or frighten to reduce effectiveness
     * - Action denial: Stunned removes actions, reactions, and movement
     * - Save optimization: Target low saves (STR for wizards, INT for beasts)
     *
     * **Condition Synergies:**
     * - Paralyzed: Attack rolls auto-crit within 5 feet (massive damage)
     * - Restrained: Advantage on attacks against target, disadvantage on DEX saves
     * - Prone: Advantage on melee attacks, disadvantage on ranged attacks
     * - Invisible: Advantage on attacks, disadvantage on attacks against you
     *
     * **Reference Data:**
     * - 15 conditions in D&D 5e
     * - Most common: Poisoned (~8 spells), Charmed (~12 spells), Frightened (~8 spells)
     * - Most powerful: Paralyzed (auto-crits), Stunned (no actions), Incapacitated
     * - Duration: Varies from 1 round to 1 minute (concentration) to permanent
     *
     * @param Condition $condition The condition (by ID or slug)
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function spells(Condition $condition)
    {
        $perPage = request()->input('per_page', 50);

        $spells = $condition->spells()
            ->with(['spellSchool', 'sources', 'tags'])
            ->orderBy('name')
            ->paginate($perPage);

        return SpellResource::collection($spells);
    }

    /**
     * List all monsters that inflict this condition
     *
     * Returns a paginated list of monsters that can inflict this condition through
     * their attacks, traits, special abilities, or innate spellcasting. Useful for
     * DMs designing encounters and players understanding enemy threats.
     *
     * **Basic Examples:**
     * - Poisoning monsters: `GET /api/v1/conditions/poisoned/monsters`
     * - Paralyzing monsters: `GET /api/v1/conditions/paralyzed/monsters`
     * - By ID: `GET /api/v1/conditions/5/monsters`
     * - Pagination: `GET /api/v1/conditions/frightened/monsters?per_page=25`
     *
     * **Common Condition Monsters:**
     * - Poisoned: Yuan-ti, Giant Spiders, Carrion Crawlers (~40 monsters)
     * - Paralyzed: Ghouls, Gelatinous Cubes, Beholders (paralysis ray) (~25 monsters)
     * - Frightened: Dragons (frightful presence), Banshees, Death Knights (~30 monsters)
     * - Charmed: Succubus/Incubus, Vampires, Sirens (~15 monsters)
     * - Stunned: Mind Flayers (mind blast), Monks (stunning strike) (~10 monsters)
     * - Restrained: Giant Spiders (webs), Ropers, Vine Blights (~20 monsters)
     * - Blinded: Umber Hulks (confusing gaze), Basilisks (~8 monsters)
     * - Petrified: Basilisks, Medusas, Cockatrices (~6 monsters)
     * - Grappled: Giant Octopuses, Mimics, Ropers (~35 monsters)
     *
     * **DM Encounter Design:**
     * - Threat assessment: Identify monsters with debilitating conditions
     * - Tactical variety: Mix damage dealers with control monsters
     * - Save targeting: Combine STR/DEX conditions with INT/WIS/CHA conditions
     * - Difficulty scaling: Paralysis/Stun can swing encounters dramatically
     *
     * **Player Preparation:**
     * - Condition immunity: Paladins (Aura of Protection), Monks (Diamond Soul)
     * - Lesser Restoration: Cures poisoned, paralyzed, blinded, deafened
     * - Greater Restoration: Cures charmed, petrified, stunned, exhaustion
     * - Protection spells: Protection from Poison, Heroes' Feast (poison immunity)
     *
     * **Dangerous Monster Conditions:**
     * - Paralyzed: Auto-crits from melee attacks (ghouls, gelatinous cubes)
     * - Petrified: Permanent until Greater Restoration (medusas, basilisks)
     * - Stunned: No actions, failed STR/DEX saves (mind flayers)
     * - Frightened: Cannot move closer (ancient dragons, death knights)
     *
     * **Condition Delivery Mechanisms:**
     * - Saving throws: Most common (CON for poison, WIS for charm/fear)
     * - Attack hits: Ghoul claws (paralysis), spider bites (poison)
     * - Failed ability checks: Gelatinous cube engulf (paralysis)
     * - Aura effects: Dragon frightful presence (WIS save), banshee wail
     *
     * **Reference Data:**
     * - 15 conditions total
     * - Most common monster conditions: Poisoned (~40), Frightened (~30), Grappled (~35)
     * - Most dangerous: Paralyzed (auto-crits), Petrified (permanent), Stunned (helpless)
     * - CR correlation: Higher CR monsters inflict more conditions simultaneously
     *
     * @param Condition $condition The condition (by ID or slug)
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function monsters(Condition $condition)
    {
        $perPage = request()->input('per_page', 50);

        $monsters = $condition->monsters()
            ->with(['size', 'sources'])
            ->orderBy('name')
            ->paginate($perPage);

        return MonsterResource::collection($monsters);
    }
}
