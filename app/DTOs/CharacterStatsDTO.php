<?php

namespace App\DTOs;

use App\Models\Character;
use App\Models\ClassFeature;
use App\Models\Feat;
use App\Models\Skill;
use App\Services\CharacterStatCalculator;
use App\Services\HitDiceService;
use App\Services\SpellManagerService;
use Illuminate\Support\Str;

/**
 * Data Transfer Object for computed character statistics.
 *
 * Encapsulates all derived stats for a D&D 5e character.
 * Issue #255: Enhanced to include full skills, saving throw proficiencies, speed, and passive scores.
 */
class CharacterStatsDTO
{
    public function __construct(
        public readonly int $characterId,
        public readonly int $level,
        public readonly int $proficiencyBonus,
        public readonly array $abilityScores,
        public readonly array $abilityModifiers,
        public readonly array $savingThrows,
        public readonly ?int $armorClass,
        public readonly ?int $maxHitPoints,
        public readonly ?int $currentHitPoints,
        public readonly int $tempHitPoints,
        public readonly ?array $spellcasting,
        public readonly array $spellSlots,
        public readonly ?int $preparationLimit,
        public readonly int $preparedSpellCount,
        // Derived combat stats
        public readonly ?int $initiativeBonus,
        public readonly ?int $passivePerception,
        public readonly ?int $passiveInvestigation,
        public readonly ?int $passiveInsight,
        public readonly ?int $carryingCapacity,
        public readonly ?int $pushDragLift,
        // Hit dice
        public readonly array $hitDice,
        // Issue #255: New properties
        public readonly array $skills,
        public readonly array $speed,
        public readonly array $passive,
        // Issue #417: Defensive traits
        public readonly array $damageResistances,
        public readonly array $damageImmunities,
        public readonly array $damageVulnerabilities,
        public readonly array $conditionAdvantages,
        public readonly array $conditionDisadvantages,
        public readonly array $conditionImmunities,
        // Issue #429: Skill check advantages
        public readonly array $skillAdvantages,
        // Issue #497: Fighting styles and combat modifiers
        public readonly array $fightingStyles,
        public readonly int $rangedAttackBonus,
        public readonly int $meleeDamageBonus,
        // Issue #498.3.3: Encumbrance tracking
        public readonly float $currentWeight,
        public readonly ?array $encumbrance,
        // Issue #498.3.1: Weapon attack/damage calculation
        public readonly array $weapons,
        // Issue #675: Spell preparation method for spellcasters
        public readonly ?string $preparationMethod,
    ) {}

    /**
     * Build stats DTO from a Character model.
     */
    public static function fromCharacter(Character $character, CharacterStatCalculator $calculator, ?SpellManagerService $spellManagerService = null): self
    {
        $level = $character->total_level;
        $proficiencyBonus = $calculator->proficiencyBonus($level);

        // Ability scores and modifiers (includes racial bonuses)
        $abilityScores = $character->getFinalAbilityScoresArray();
        $abilityModifiers = [];
        foreach ($abilityScores as $code => $score) {
            $abilityModifiers[$code] = $score !== null ? $calculator->abilityModifier($score) : null;
        }

        // Get saving throw proficiencies from primary class
        $savingThrowProficiencies = self::getSavingThrowProficiencies($character);

        // Saving throws with proficiency info
        $savingThrows = [];
        foreach ($abilityScores as $code => $score) {
            $baseMod = $abilityModifiers[$code];
            $proficient = $savingThrowProficiencies[$code] ?? false;
            $total = $baseMod !== null
                ? $baseMod + ($proficient ? $proficiencyBonus : 0)
                : null;

            $savingThrows[$code] = [
                'modifier' => $baseMod,
                'proficient' => $proficient,
                'total' => $total,
            ];
        }

        // Spellcasting info (uses primary class)
        $spellcasting = null;
        $preparationLimit = null;

        // Issue #618: Use SpellManagerService for enriched spell slots with tracking
        // Returns: ['slots' => [...], 'pact_magic' => [...], 'preparation_limit' => int, 'prepared_count' => int]
        $spellSlots = $spellManagerService
            ? $spellManagerService->getSpellSlots($character)
            : ['slots' => [], 'pact_magic' => null, 'preparation_limit' => null, 'prepared_count' => 0];

        $primaryClass = $character->primary_class;
        if ($primaryClass) {
            $spellcastingAbility = $primaryClass->effective_spellcasting_ability;

            if ($spellcastingAbility) {
                $abilityCode = $spellcastingAbility->code;
                $abilityMod = $abilityModifiers[$abilityCode] ?? 0;

                $spellcasting = [
                    'ability' => $abilityCode,
                    'ability_modifier' => $abilityMod,
                    'spell_save_dc' => $calculator->spellSaveDC($proficiencyBonus, $abilityMod),
                    'spell_attack_bonus' => $proficiencyBonus + $abilityMod,
                ];

                // Get preparation limit from the enriched spell slots data
                $preparationLimit = $spellSlots['preparation_limit'] ?? null;
            }
        }

        // Get prepared spell count from the enriched spell slots data
        $preparedSpellCount = $spellSlots['prepared_count'] ?? 0;

        // Calculate derived combat stats
        // Use null to properly track missing ability scores (not 0, which is a valid modifier)
        $dexMod = $abilityModifiers['DEX'];
        $wisMod = $abilityModifiers['WIS'];
        $intMod = $abilityModifiers['INT'];
        $strScore = $abilityScores['STR'];

        // Get skill proficiencies for passive skills (eager-load if not already loaded)
        if (! $character->relationLoaded('proficiencies')) {
            $character->load('proficiencies.skill');
        }
        $skillProficiencies = self::getSkillProficiencies($character);

        // Detect Jack of All Trades early (needed for initiative and skills)
        $hasJackOfAllTrades = self::hasJackOfAllTrades($character);
        $halfProficiencyBonus = (int) floor($proficiencyBonus / 2);

        // Initiative bonus (DEX modifier + bonuses from feats/items)
        // Jack of All Trades adds half proficiency bonus (it's an ability check)
        $initiativeBonus = $dexMod !== null
            ? $calculator->calculateInitiative($dexMod, $calculator->getInitiativeModifiers($character))
            : null;

        if ($hasJackOfAllTrades && $initiativeBonus !== null) {
            $initiativeBonus += $halfProficiencyBonus;
        }

        // Passive skills - helper to reduce duplication
        $calculatePassive = fn (?int $mod, string $skill) => $mod !== null
            ? $calculator->calculatePassiveSkill(
                $mod,
                $skillProficiencies[$skill]['proficient'] ?? false,
                $skillProficiencies[$skill]['expertise'] ?? false,
                $proficiencyBonus
            )
            : null;

        $passivePerception = $calculatePassive($wisMod, 'perception');
        $passiveInvestigation = $calculatePassive($intMod, 'investigation');
        $passiveInsight = $calculatePassive($wisMod, 'insight');

        // Grouped passive scores object
        $passive = [
            'perception' => $passivePerception,
            'investigation' => $passiveInvestigation,
            'insight' => $passiveInsight,
        ];

        // Carrying capacity (based on STR and size)
        $size = $character->size ?? 'Medium';
        $carryingCapacity = $strScore !== null
            ? $calculator->calculateCarryingCapacity($strScore, $size)
            : null;
        $pushDragLift = $strScore !== null
            ? $calculator->calculatePushDragLift($strScore, $size)
            : null;

        // Current weight and encumbrance (Issue #498.3.3)
        $currentWeight = self::calculateCurrentWeight($character);
        $encumbrance = $strScore !== null
            ? $calculator->calculateEncumbrance($strScore, $currentWeight)
            : null;

        // Get hit dice data
        $hitDiceService = app(HitDiceService::class);
        $hitDiceData = $hitDiceService->getHitDice($character);

        // Detect Reliable Talent for skill calculations (Issue #497)
        // Note: hasJackOfAllTrades already detected earlier for initiative
        $hasReliableTalent = self::hasReliableTalent($character);

        // Detect Silver Tongue for minimum roll indicators (Issue #672)
        $hasSilverTongue = self::hasSilverTongue($character);

        // Build full skills array
        $skills = self::buildSkills($abilityModifiers, $skillProficiencies, $proficiencyBonus, $calculator, $hasJackOfAllTrades, $hasReliableTalent, $hasSilverTongue);

        // Build speed array
        $speed = self::buildSpeed($character);

        // Build defensive traits
        $defensiveTraits = self::buildDefensiveTraits($character);

        // Build fighting styles and combat modifiers (Issue #497)
        $fightingStyleData = self::buildFightingStyles($character);

        // Build weapon stats (Issue #498.3.1)
        $weapons = self::buildWeaponStats($character, $abilityModifiers, $proficiencyBonus);

        // Compute preparation method (Issue #675)
        $preparationMethod = self::computePreparationMethod($character);

        return new self(
            characterId: $character->id,
            level: $level,
            proficiencyBonus: $proficiencyBonus,
            abilityScores: $abilityScores,
            abilityModifiers: $abilityModifiers,
            savingThrows: $savingThrows,
            armorClass: $character->armor_class,
            maxHitPoints: $character->max_hit_points,
            currentHitPoints: $character->current_hit_points,
            tempHitPoints: $character->temp_hit_points ?? 0,
            spellcasting: $spellcasting,
            spellSlots: $spellSlots,
            preparationLimit: $preparationLimit,
            preparedSpellCount: $preparedSpellCount,
            initiativeBonus: $initiativeBonus,
            passivePerception: $passivePerception,
            passiveInvestigation: $passiveInvestigation,
            passiveInsight: $passiveInsight,
            carryingCapacity: $carryingCapacity,
            pushDragLift: $pushDragLift,
            hitDice: $hitDiceData['hit_dice'],
            skills: $skills,
            speed: $speed,
            passive: $passive,
            damageResistances: $defensiveTraits['damage_resistances'],
            damageImmunities: $defensiveTraits['damage_immunities'],
            damageVulnerabilities: $defensiveTraits['damage_vulnerabilities'],
            conditionAdvantages: $defensiveTraits['condition_advantages'],
            conditionDisadvantages: $defensiveTraits['condition_disadvantages'],
            conditionImmunities: $defensiveTraits['condition_immunities'],
            skillAdvantages: $defensiveTraits['skill_advantages'],
            fightingStyles: $fightingStyleData['styles'],
            rangedAttackBonus: $fightingStyleData['ranged_attack_bonus'],
            meleeDamageBonus: $fightingStyleData['melee_damage_bonus'],
            currentWeight: $currentWeight,
            encumbrance: $encumbrance,
            weapons: $weapons,
            preparationMethod: $preparationMethod,
        );
    }

    /**
     * Build weapon stats for all equipped weapons.
     *
     * @param  array<string, int|null>  $abilityModifiers
     * @return array<int, array{name: string, damage_dice: string|null, attack_bonus: int, damage_bonus: int, ability_used: string, is_proficient: bool}>
     */
    private static function buildWeaponStats(Character $character, array $abilityModifiers, int $proficiencyBonus): array
    {
        // Load equipment with items and properties if not loaded
        if (! $character->relationLoaded('equipment')) {
            $character->load(['equipment.item.itemType', 'equipment.item.properties']);
        }

        // Load proficiencies for checking
        if (! $character->relationLoaded('proficiencies')) {
            $character->load('proficiencies');
        }

        $weapons = [];
        $strMod = $abilityModifiers['STR'] ?? 0;
        $dexMod = $abilityModifiers['DEX'] ?? 0;

        foreach ($character->equipment as $equipment) {
            // Skip non-equipped items
            if (! $equipment->equipped) {
                continue;
            }

            // Skip if no item or not a weapon
            if (! $equipment->item || ! $equipment->isWeapon()) {
                continue;
            }

            $item = $equipment->item;
            $itemType = $item->itemType;

            // Determine if finesse weapon (can use DEX for melee)
            $isFinesse = $item->properties->contains('code', 'F');
            $isRanged = $itemType?->code === 'R';

            // Determine which ability to use
            if ($isRanged) {
                $abilityMod = $dexMod;
                $abilityUsed = 'DEX';
            } elseif ($isFinesse) {
                // Use whichever is higher
                if ($dexMod > $strMod) {
                    $abilityMod = $dexMod;
                    $abilityUsed = 'DEX';
                } else {
                    $abilityMod = $strMod;
                    $abilityUsed = 'STR';
                }
            } else {
                $abilityMod = $strMod;
                $abilityUsed = 'STR';
            }

            // Check proficiency
            $isProficient = self::isWeaponProficient($character, $item);

            // Calculate attack bonus = ability mod + proficiency (if proficient)
            $attackBonus = $abilityMod + ($isProficient ? $proficiencyBonus : 0);

            // Calculate damage bonus = ability mod
            $damageBonus = $abilityMod;

            $weapons[] = [
                'name' => $item->name,
                'damage_dice' => $item->damage_dice,
                'attack_bonus' => $attackBonus,
                'damage_bonus' => $damageBonus,
                'ability_used' => $abilityUsed,
                'is_proficient' => $isProficient,
            ];
        }

        return $weapons;
    }

    /**
     * Check if character is proficient with a weapon.
     */
    private static function isWeaponProficient(Character $character, \App\Models\Item $item): bool
    {
        // Generate the expected proficiency slug (e.g., "core:longsword")
        $weaponSlug = 'core:'.Str::slug($item->name);

        // Check for specific weapon name proficiency
        $hasSpecific = $character->proficiencies
            ->where('proficiency_type_slug', $weaponSlug)
            ->isNotEmpty();

        if ($hasSpecific) {
            return true;
        }

        // Check for weapon category proficiency (Simple Weapons, Martial Weapons)
        // This would require more complex logic based on item properties
        // For now, just check specific weapon names
        return false;
    }

    /**
     * Calculate total weight of all equipment.
     */
    private static function calculateCurrentWeight(Character $character): float
    {
        // Load equipment with items if not already loaded
        if (! $character->relationLoaded('equipment')) {
            $character->load('equipment.item');
        }

        return $character->equipment->reduce(function (float $total, $equipment) {
            $weight = $equipment->item?->weight ?? 0;

            return $total + ($weight * $equipment->quantity);
        }, 0.0);
    }

    /**
     * Get saving throw proficiency status from primary class and feats.
     *
     * Issue #497: Also checks for Resilient feat variants which grant
     * proficiency in a specific saving throw.
     *
     * @return array<string, bool> Keyed by ability code (STR, DEX, etc.)
     */
    private static function getSavingThrowProficiencies(Character $character): array
    {
        $result = [
            'STR' => false,
            'DEX' => false,
            'CON' => false,
            'INT' => false,
            'WIS' => false,
            'CHA' => false,
        ];

        // Check primary class for saving throw proficiencies
        $primaryClass = $character->primary_class;
        if ($primaryClass) {
            // Load proficiencies if not loaded
            if (! $primaryClass->relationLoaded('proficiencies')) {
                $primaryClass->load('proficiencies');
            }

            // Map ability names to codes
            $nameToCode = [
                'Strength' => 'STR',
                'Dexterity' => 'DEX',
                'Constitution' => 'CON',
                'Intelligence' => 'INT',
                'Wisdom' => 'WIS',
                'Charisma' => 'CHA',
            ];

            $savingThrows = $primaryClass->proficiencies
                ->where('proficiency_type', 'saving_throw');

            foreach ($savingThrows as $prof) {
                $code = $nameToCode[$prof->proficiency_name] ?? null;
                if ($code) {
                    $result[$code] = true;
                }
            }
        }

        // Check for Resilient feat variants (Issue #497)
        // Resilient feats have slugs like "phb:resilient-wisdom" or "test:resilient-dexterity"
        $resilientSuffixToCode = [
            'resilient-strength' => 'STR',
            'resilient-dexterity' => 'DEX',
            'resilient-constitution' => 'CON',
            'resilient-intelligence' => 'INT',
            'resilient-wisdom' => 'WIS',
            'resilient-charisma' => 'CHA',
        ];

        $featSlugs = $character->features()
            ->where('feature_type', Feat::class)
            ->pluck('feature_slug')
            ->toArray();

        foreach ($featSlugs as $slug) {
            // Extract the suffix after the source prefix (e.g., "phb:" or "test:")
            $parts = explode(':', $slug);
            $suffix = end($parts);

            if (isset($resilientSuffixToCode[$suffix])) {
                $result[$resilientSuffixToCode[$suffix]] = true;
            }
        }

        return $result;
    }

    /**
     * Get skill proficiency and expertise status for a character.
     *
     * Expects proficiencies.skill to be eager-loaded to avoid N+1 queries.
     *
     * @return array<string, array{proficient: bool, expertise: bool}>
     */
    private static function getSkillProficiencies(Character $character): array
    {
        $result = [];

        // Use the already-loaded proficiencies relation (filtered in memory)
        $proficiencies = $character->proficiencies
            ->filter(fn ($p) => $p->skill_slug !== null);

        foreach ($proficiencies as $proficiency) {
            if ($proficiency->skill) {
                $slug = $proficiency->skill->slug;
                $result[$slug] = [
                    'proficient' => true,
                    'expertise' => $proficiency->expertise ?? false,
                ];
            }
        }

        return $result;
    }

    /**
     * Build complete skills array with all 18 skills.
     *
     * @return array<int, array{
     *   name: string,
     *   slug: string,
     *   ability: string,
     *   ability_modifier: int|null,
     *   proficient: bool,
     *   expertise: bool,
     *   modifier: int|null,
     *   passive: int|null,
     *   has_reliable_talent: bool,
     *   minimum_roll: int|null,
     *   minimum_total: int|null
     * }>
     */
    private static function buildSkills(
        array $abilityModifiers,
        array $skillProficiencies,
        int $proficiencyBonus,
        CharacterStatCalculator $calculator,
        bool $hasJackOfAllTrades = false,
        bool $hasReliableTalent = false,
        bool $hasSilverTongue = false
    ): array {
        // Note: This queries 18 skills per request. Acceptable trade-off as skills
        // rarely change and the query is small. Consider caching if profiling shows issues.
        $skills = Skill::with('abilityScore')->get();

        // Calculate half proficiency bonus for Jack of All Trades
        $halfProficiencyBonus = (int) floor($proficiencyBonus / 2);

        // Silver Tongue applies only to these skills (Issue #672)
        $silverTongueSkills = ['core:persuasion', 'core:deception'];

        return $skills->map(function ($skill) use ($abilityModifiers, $skillProficiencies, $proficiencyBonus, $calculator, $hasJackOfAllTrades, $hasReliableTalent, $hasSilverTongue, $halfProficiencyBonus, $silverTongueSkills) {
            // Defensive: skip skills without ability score (shouldn't happen with proper seeding)
            if (! $skill->abilityScore) {
                return null;
            }

            $abilityCode = $skill->abilityScore->code;
            $abilityMod = $abilityModifiers[$abilityCode];
            $profData = $skillProficiencies[$skill->slug] ?? ['proficient' => false, 'expertise' => false];
            $proficient = $profData['proficient'];
            $expertise = $profData['expertise'];

            $modifier = $abilityMod !== null
                ? $calculator->skillModifier($abilityMod, $proficient, $expertise, $proficiencyBonus)
                : null;

            // Jack of All Trades: Add half proficiency to non-proficient skills
            if ($hasJackOfAllTrades && ! $proficient && $modifier !== null) {
                $modifier += $halfProficiencyBonus;
            }

            $passive = $abilityMod !== null
                ? $calculator->calculatePassiveSkill($abilityMod, $proficient, $expertise, $proficiencyBonus)
                : null;

            // Jack of All Trades also affects passive skills
            if ($hasJackOfAllTrades && ! $proficient && $passive !== null) {
                $passive += $halfProficiencyBonus;
            }

            // Reliable Talent: Only applies to proficient skills
            $skillHasReliableTalent = $hasReliableTalent && $proficient;

            // Issue #672: Calculate minimum roll indicators
            // Reliable Talent: applies to all proficient ability checks
            // Silver Tongue: applies only to Persuasion and Deception (regardless of proficiency)
            $hasMinimumRoll = $skillHasReliableTalent
                || ($hasSilverTongue && in_array($skill->slug, $silverTongueSkills, true));

            $minimumRoll = $hasMinimumRoll ? 10 : null;
            $minimumTotal = ($hasMinimumRoll && $modifier !== null) ? (10 + $modifier) : null;

            return [
                'name' => $skill->name,
                'slug' => $skill->slug,
                'ability' => $abilityCode,
                'ability_modifier' => $abilityMod,
                'proficient' => $proficient,
                'expertise' => $expertise,
                'modifier' => $modifier,
                'passive' => $passive,
                'has_reliable_talent' => $skillHasReliableTalent,
                'minimum_roll' => $minimumRoll,
                'minimum_total' => $minimumTotal,
            ];
        })->filter()->sortBy('name')->values()->all();
    }

    /**
     * Build speed array from character's race.
     *
     * @return array{walk: int, fly: int|null, swim: int|null, climb: int|null, burrow: int|null}
     */
    private static function buildSpeed(Character $character): array
    {
        $race = $character->race;

        return [
            'walk' => $race?->speed ?? 30,
            'fly' => $race?->fly_speed,
            'swim' => $race?->swim_speed,
            'climb' => $race?->climb_speed,
            'burrow' => null, // Not currently tracked on races
        ];
    }

    /**
     * Extract defensive traits from an entity (Race or Feat) that uses HasConditions and HasModifiers traits.
     *
     * @param  \App\Models\Race|\App\Models\Feat  $entity
     */
    private static function extractDefensiveTraitsFromEntity(
        $entity,
        string $sourceName,
        array &$damageResistances,
        array &$damageImmunities,
        array &$damageVulnerabilities,
        array &$conditionAdvantages,
        array &$conditionDisadvantages,
        array &$conditionImmunities,
        array &$skillAdvantages
    ): void {
        // Defensive: controller should eager-load modifiers.damageType and modifiers.skill,
        // but load if called directly (e.g., tests)
        if (! $entity->relationLoaded('modifiers')) {
            $entity->load(['modifiers.damageType', 'modifiers.skill']);
        }

        // Process damage modifiers and skill advantages
        foreach ($entity->modifiers as $modifier) {
            $category = $modifier->modifier_category;

            if (in_array($category, ['damage_resistance', 'damage_immunity', 'damage_vulnerability'])) {
                // Determine type from damage_type relationship or condition field
                $type = $modifier->damageType?->name ?? $modifier->condition;

                if ($type) {
                    $entry = [
                        'type' => $type,
                        'condition' => $modifier->damageType ? $modifier->condition : null,
                        'source' => $sourceName,
                    ];

                    match ($category) {
                        'damage_resistance' => $damageResistances[] = $entry,
                        'damage_immunity' => $damageImmunities[] = $entry,
                        'damage_vulnerability' => $damageVulnerabilities[] = $entry,
                        default => null,
                    };
                }
            }

            // Issue #429: Extract skill check advantages
            if ($category === 'skill_advantage' && $modifier->skill) {
                $skillAdvantages[] = [
                    'skill' => $modifier->skill->name,
                    'skill_slug' => $modifier->skill->slug,
                    'condition' => $modifier->condition,
                    'source' => $sourceName,
                ];
            }
        }

        // Defensive: controller should eager-load, but load if called directly (e.g., tests)
        if (! $entity->relationLoaded('conditions')) {
            $entity->load('conditions.condition');
        }

        // Process condition effects
        foreach ($entity->conditions as $entityCondition) {
            if (! $entityCondition->condition) {
                continue;
            }

            $entry = [
                'condition' => $entityCondition->condition->name,
                'effect' => $entityCondition->effect_type,
                'source' => $sourceName,
            ];

            match ($entityCondition->effect_type) {
                'advantage' => $conditionAdvantages[] = $entry,
                'disadvantage' => $conditionDisadvantages[] = $entry,
                'immunity' => $conditionImmunities[] = $entry,
                default => null,
            };
        }
    }

    /**
     * Build defensive traits from character's race and feats.
     *
     * Aggregates damage resistances/immunities/vulnerabilities, condition effects,
     * and skill check advantages from both race and feats into categorized arrays.
     *
     * @return array{
     *   damage_resistances: array,
     *   damage_immunities: array,
     *   damage_vulnerabilities: array,
     *   condition_advantages: array,
     *   condition_disadvantages: array,
     *   condition_immunities: array,
     *   skill_advantages: array
     * }
     */
    private static function buildDefensiveTraits(Character $character): array
    {
        $damageResistances = [];
        $damageImmunities = [];
        $damageVulnerabilities = [];
        $conditionAdvantages = [];
        $conditionDisadvantages = [];
        $conditionImmunities = [];
        $skillAdvantages = [];

        // Process race defensive traits
        if ($race = $character->race) {
            self::extractDefensiveTraitsFromEntity(
                $race,
                $race->name,
                $damageResistances,
                $damageImmunities,
                $damageVulnerabilities,
                $conditionAdvantages,
                $conditionDisadvantages,
                $conditionImmunities,
                $skillAdvantages
            );
        }

        // Process feats defensive traits
        if (! $character->relationLoaded('features')) {
            $character->load('features');
        }

        // Get feats from character features
        $featFeatures = $character->features->filter(
            fn ($f) => $f->feature_type === \App\Models\Feat::class
        );

        foreach ($featFeatures as $characterFeature) {
            // Defensive: controller should eager-load via features.feature
            if (! $characterFeature->relationLoaded('feature')) {
                $characterFeature->load('feature');
            }

            $feat = $characterFeature->feature;

            if (! $feat) {
                \Log::warning("Character {$character->id} has orphaned feature record: {$characterFeature->id}");

                continue;
            }

            self::extractDefensiveTraitsFromEntity(
                $feat,
                $feat->name,
                $damageResistances,
                $damageImmunities,
                $damageVulnerabilities,
                $conditionAdvantages,
                $conditionDisadvantages,
                $conditionImmunities,
                $skillAdvantages
            );
        }

        return [
            'damage_resistances' => $damageResistances,
            'damage_immunities' => $damageImmunities,
            'damage_vulnerabilities' => $damageVulnerabilities,
            'condition_advantages' => $conditionAdvantages,
            'condition_disadvantages' => $conditionDisadvantages,
            'condition_immunities' => $conditionImmunities,
            'skill_advantages' => $skillAdvantages,
        ];
    }

    /**
     * Build fighting styles and combat modifiers from character's class features.
     *
     * Issue #497: Extracts fighting styles the character has selected and
     * calculates the corresponding combat bonuses:
     * - Archery: +2 to ranged weapon attack rolls
     * - Defense: +1 AC while wearing armor (handled in ArmorClass calculation)
     * - Dueling: +2 damage with one-handed melee weapon
     * - Great Weapon Fighting: Reroll 1s/2s on damage (not calculable)
     * - Two-Weapon Fighting: Add modifier to off-hand (not calculable)
     * - Protection: Reaction-based (not calculable)
     *
     * @return array{styles: array<string>, ranged_attack_bonus: int, melee_damage_bonus: int}
     */
    private static function buildFightingStyles(Character $character): array
    {
        $styles = [];
        $rangedAttackBonus = 0;
        $meleeDamageBonus = 0;

        // Load features if not loaded
        if (! $character->relationLoaded('features')) {
            $character->load('features');
        }

        // Get class features that are fighting styles
        $classFeatures = $character->features->filter(
            fn ($f) => $f->feature_type === ClassFeature::class
        );

        // Fighting style name patterns to match and their extracted names
        // Matches: "Fighting Style: Archery", "Fighting Style: Archery (Champion)", etc.
        $fightingStylePattern = '/^Fighting Style:\s*(\w+)/i';

        foreach ($classFeatures as $characterFeature) {
            // Load the actual feature if not loaded
            if (! $characterFeature->relationLoaded('feature')) {
                $characterFeature->load('feature');
            }

            $feature = $characterFeature->feature;
            if (! $feature) {
                continue;
            }

            // Check if this is a fighting style
            if (preg_match($fightingStylePattern, $feature->feature_name, $matches)) {
                $styleName = $matches[1];
                $styles[] = $styleName;

                // Apply combat bonuses based on style
                match (strtolower($styleName)) {
                    'archery' => $rangedAttackBonus += 2,
                    'dueling' => $meleeDamageBonus += 2,
                    default => null,
                };
            }
        }

        return [
            'styles' => array_unique($styles),
            'ranged_attack_bonus' => $rangedAttackBonus,
            'melee_damage_bonus' => $meleeDamageBonus,
        ];
    }

    /**
     * Check if character has the Jack of All Trades feature.
     *
     * Jack of All Trades (Bard level 2): Add half proficiency bonus to
     * all ability checks that don't already include proficiency bonus.
     */
    private static function hasJackOfAllTrades(Character $character): bool
    {
        // Load features if not loaded
        if (! $character->relationLoaded('features')) {
            $character->load('features');
        }

        // Check for Jack of All Trades class feature
        foreach ($character->features as $characterFeature) {
            if ($characterFeature->feature_type !== ClassFeature::class) {
                continue;
            }

            // Load the actual feature if not loaded
            if (! $characterFeature->relationLoaded('feature')) {
                $characterFeature->load('feature');
            }

            $feature = $characterFeature->feature;
            if ($feature && $feature->feature_name === 'Jack of All Trades') {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if character has the Reliable Talent feature.
     *
     * Reliable Talent (Rogue level 11): Minimum roll of 10 on proficient
     * skill checks (treat any roll of 9 or lower as a 10).
     */
    private static function hasReliableTalent(Character $character): bool
    {
        return self::hasClassFeatureByName($character, 'Reliable Talent');
    }

    /**
     * Check if character has the Silver Tongue feature.
     *
     * Silver Tongue (College of Eloquence Bard level 3): Minimum roll of 10
     * on Charisma (Persuasion) and Charisma (Deception) checks.
     *
     * Issue #672: Part of minimum roll indicators feature.
     */
    private static function hasSilverTongue(Character $character): bool
    {
        return self::hasClassFeatureByName($character, 'Silver Tongue');
    }

    /**
     * Check if character has a class feature by name.
     *
     * Helper method to detect features by their feature_name field.
     */
    private static function hasClassFeatureByName(Character $character, string $featureName): bool
    {
        // Load features if not loaded
        if (! $character->relationLoaded('features')) {
            $character->load('features');
        }

        // Check for the named class feature
        foreach ($character->features as $characterFeature) {
            if ($characterFeature->feature_type !== ClassFeature::class) {
                continue;
            }

            // Load the actual feature if not loaded
            if (! $characterFeature->relationLoaded('feature')) {
                $characterFeature->load('feature');
            }

            $feature = $characterFeature->feature;
            // Match feature name, stripping any parenthetical suffix like "(College of Eloquence)"
            if ($feature) {
                $baseName = preg_replace('/\s*\([^)]*\)\s*$/', '', $feature->feature_name);
                if ($baseName === $featureName) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Compute the spell preparation method for a character.
     *
     * Issue #675: Returns the preparation method based on character's class(es):
     * - "known": Bard, Sorcerer, Warlock, Ranger (spells always available)
     * - "spellbook": Wizard (prepare from spellbook)
     * - "prepared": Cleric, Druid, Paladin, Artificer (prepare daily)
     * - "mixed": Multiclass with different preparation methods
     * - null: Non-spellcaster or no class
     */
    private static function computePreparationMethod(Character $character): ?string
    {
        // Load characterClasses with their class models if not loaded
        if (! $character->relationLoaded('characterClasses')) {
            $character->load('characterClasses.characterClass.parentClass');
        }

        // Collect unique preparation methods from all classes
        $methods = [];

        foreach ($character->characterClasses as $pivot) {
            $class = $pivot->characterClass;
            if (! $class) {
                continue;
            }

            // Use the accessor which handles subclass inheritance
            $method = $class->spell_preparation_method;
            if ($method !== null) {
                $methods[] = $method;
            }
        }

        // No spellcasting classes
        if (empty($methods)) {
            return null;
        }

        // Get unique methods
        $uniqueMethods = array_unique($methods);

        // Single method or all same
        if (count($uniqueMethods) === 1) {
            return $uniqueMethods[0];
        }

        // Multiple different methods
        return 'mixed';
    }
}
