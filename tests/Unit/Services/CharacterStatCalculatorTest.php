<?php

namespace Tests\Unit\Services;

use App\Services\CharacterStatCalculator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterStatCalculatorTest extends TestCase
{
    private CharacterStatCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new CharacterStatCalculator;
    }

    // =====================
    // Ability Modifier Tests
    // =====================

    #[Test]
    public function it_calculates_ability_modifier_for_low_scores(): void
    {
        $this->assertEquals(-5, $this->calculator->abilityModifier(1));
        $this->assertEquals(-4, $this->calculator->abilityModifier(2));
        $this->assertEquals(-4, $this->calculator->abilityModifier(3));
        $this->assertEquals(-2, $this->calculator->abilityModifier(6));
        $this->assertEquals(-2, $this->calculator->abilityModifier(7));
        $this->assertEquals(-1, $this->calculator->abilityModifier(8));
        $this->assertEquals(-1, $this->calculator->abilityModifier(9));
    }

    #[Test]
    public function it_calculates_ability_modifier_for_average_scores(): void
    {
        $this->assertEquals(0, $this->calculator->abilityModifier(10));
        $this->assertEquals(0, $this->calculator->abilityModifier(11));
    }

    #[Test]
    public function it_calculates_ability_modifier_for_high_scores(): void
    {
        $this->assertEquals(1, $this->calculator->abilityModifier(12));
        $this->assertEquals(1, $this->calculator->abilityModifier(13));
        $this->assertEquals(2, $this->calculator->abilityModifier(14));
        $this->assertEquals(2, $this->calculator->abilityModifier(15));
        $this->assertEquals(3, $this->calculator->abilityModifier(16));
        $this->assertEquals(3, $this->calculator->abilityModifier(17));
        $this->assertEquals(4, $this->calculator->abilityModifier(18));
        $this->assertEquals(4, $this->calculator->abilityModifier(19));
        $this->assertEquals(5, $this->calculator->abilityModifier(20));
    }

    // ========================
    // Proficiency Bonus Tests
    // ========================

    #[Test]
    public function it_calculates_proficiency_bonus_by_level(): void
    {
        // +2 at levels 1-4
        $this->assertEquals(2, $this->calculator->proficiencyBonus(1));
        $this->assertEquals(2, $this->calculator->proficiencyBonus(2));
        $this->assertEquals(2, $this->calculator->proficiencyBonus(3));
        $this->assertEquals(2, $this->calculator->proficiencyBonus(4));

        // +3 at levels 5-8
        $this->assertEquals(3, $this->calculator->proficiencyBonus(5));
        $this->assertEquals(3, $this->calculator->proficiencyBonus(6));
        $this->assertEquals(3, $this->calculator->proficiencyBonus(7));
        $this->assertEquals(3, $this->calculator->proficiencyBonus(8));

        // +4 at levels 9-12
        $this->assertEquals(4, $this->calculator->proficiencyBonus(9));
        $this->assertEquals(4, $this->calculator->proficiencyBonus(10));
        $this->assertEquals(4, $this->calculator->proficiencyBonus(11));
        $this->assertEquals(4, $this->calculator->proficiencyBonus(12));

        // +5 at levels 13-16
        $this->assertEquals(5, $this->calculator->proficiencyBonus(13));
        $this->assertEquals(5, $this->calculator->proficiencyBonus(14));
        $this->assertEquals(5, $this->calculator->proficiencyBonus(15));
        $this->assertEquals(5, $this->calculator->proficiencyBonus(16));

        // +6 at levels 17-20
        $this->assertEquals(6, $this->calculator->proficiencyBonus(17));
        $this->assertEquals(6, $this->calculator->proficiencyBonus(18));
        $this->assertEquals(6, $this->calculator->proficiencyBonus(19));
        $this->assertEquals(6, $this->calculator->proficiencyBonus(20));
    }

    // ====================
    // Spell Save DC Tests
    // ====================

    #[Test]
    public function it_calculates_spell_save_dc(): void
    {
        // DC = 8 + proficiency + ability modifier
        // Level 1 (+2 prof), INT 16 (+3): 8 + 2 + 3 = 13
        $this->assertEquals(13, $this->calculator->spellSaveDC(
            proficiencyBonus: 2,
            abilityModifier: 3
        ));

        // Level 5 (+3 prof), WIS 18 (+4): 8 + 3 + 4 = 15
        $this->assertEquals(15, $this->calculator->spellSaveDC(
            proficiencyBonus: 3,
            abilityModifier: 4
        ));

        // Level 17 (+6 prof), CHA 20 (+5): 8 + 6 + 5 = 19
        $this->assertEquals(19, $this->calculator->spellSaveDC(
            proficiencyBonus: 6,
            abilityModifier: 5
        ));
    }

    // =====================
    // Skill Modifier Tests
    // =====================

    #[Test]
    public function it_calculates_skill_modifier_without_proficiency(): void
    {
        // DEX 14 (+2), not proficient
        $this->assertEquals(2, $this->calculator->skillModifier(
            abilityModifier: 2,
            proficient: false,
            expertise: false,
            proficiencyBonus: 2
        ));
    }

    #[Test]
    public function it_calculates_skill_modifier_with_proficiency(): void
    {
        // DEX 14 (+2), proficient, level 1 (+2 prof)
        // Result: 2 + 2 = 4
        $this->assertEquals(4, $this->calculator->skillModifier(
            abilityModifier: 2,
            proficient: true,
            expertise: false,
            proficiencyBonus: 2
        ));
    }

    #[Test]
    public function it_calculates_skill_modifier_with_expertise(): void
    {
        // DEX 14 (+2), expertise, level 1 (+2 prof doubled = +4)
        // Result: 2 + 4 = 6
        $this->assertEquals(6, $this->calculator->skillModifier(
            abilityModifier: 2,
            proficient: true,
            expertise: true,
            proficiencyBonus: 2
        ));
    }

    #[Test]
    public function it_calculates_skill_modifier_with_expertise_at_higher_levels(): void
    {
        // STR 16 (+3), expertise, level 9 (+4 prof doubled = +8)
        // Result: 3 + 8 = 11
        $this->assertEquals(11, $this->calculator->skillModifier(
            abilityModifier: 3,
            proficient: true,
            expertise: true,
            proficiencyBonus: 4
        ));
    }

    // ==================
    // Max HP Tests
    // ==================

    #[Test]
    public function it_calculates_max_hp_for_level_1(): void
    {
        // Wizard (d6) with CON 14 (+2): 6 + 2 = 8
        $this->assertEquals(8, $this->calculator->calculateMaxHP(
            hitDie: 6,
            level: 1,
            conModifier: 2
        ));

        // Fighter (d10) with CON 16 (+3): 10 + 3 = 13
        $this->assertEquals(13, $this->calculator->calculateMaxHP(
            hitDie: 10,
            level: 1,
            conModifier: 3
        ));

        // Barbarian (d12) with CON 18 (+4): 12 + 4 = 16
        $this->assertEquals(16, $this->calculator->calculateMaxHP(
            hitDie: 12,
            level: 1,
            conModifier: 4
        ));
    }

    #[Test]
    public function it_calculates_max_hp_for_higher_levels_using_average(): void
    {
        // Wizard (d6) level 5, CON 14 (+2)
        // Level 1: 6 + 2 = 8
        // Levels 2-5: 4 × (avg 4 + 2) = 4 × 6 = 24
        // Total: 8 + 24 = 32
        $this->assertEquals(32, $this->calculator->calculateMaxHP(
            hitDie: 6,
            level: 5,
            conModifier: 2
        ));

        // Fighter (d10) level 3, CON 16 (+3)
        // Level 1: 10 + 3 = 13
        // Levels 2-3: 2 × (avg 6 + 3) = 2 × 9 = 18
        // Total: 13 + 18 = 31
        $this->assertEquals(31, $this->calculator->calculateMaxHP(
            hitDie: 10,
            level: 3,
            conModifier: 3
        ));
    }

    #[Test]
    public function it_handles_negative_con_modifier_with_minimum_1_hp(): void
    {
        // Wizard (d6) with CON 6 (-2), level 1: 6 + (-2) = 4
        $this->assertEquals(4, $this->calculator->calculateMaxHP(
            hitDie: 6,
            level: 1,
            conModifier: -2
        ));

        // Ensure minimum 1 HP even with very negative CON
        $this->assertGreaterThanOrEqual(1, $this->calculator->calculateMaxHP(
            hitDie: 6,
            level: 1,
            conModifier: -10
        ));
    }

    // ==================
    // Armor Class Tests
    // ==================

    #[Test]
    public function it_calculates_armor_class_unarmored(): void
    {
        // No armor, DEX 14 (+2): AC = 10 + 2 = 12
        $this->assertEquals(12, $this->calculator->calculateAC(
            dexModifier: 2,
            armorBaseAC: null,
            armorMaxDex: null,
            shieldBonus: 0,
            otherBonuses: 0
        ));

        // No armor, DEX 18 (+4): AC = 10 + 4 = 14
        $this->assertEquals(14, $this->calculator->calculateAC(
            dexModifier: 4,
            armorBaseAC: null,
            armorMaxDex: null,
            shieldBonus: 0,
            otherBonuses: 0
        ));
    }

    #[Test]
    public function it_calculates_armor_class_with_light_armor(): void
    {
        // Leather (11 base), DEX 16 (+3): AC = 11 + 3 = 14
        $this->assertEquals(14, $this->calculator->calculateAC(
            dexModifier: 3,
            armorBaseAC: 11,
            armorMaxDex: null,
            shieldBonus: 0,
            otherBonuses: 0
        ));

        // Studded Leather (12 base), DEX 18 (+4): AC = 12 + 4 = 16
        $this->assertEquals(16, $this->calculator->calculateAC(
            dexModifier: 4,
            armorBaseAC: 12,
            armorMaxDex: null,
            shieldBonus: 0,
            otherBonuses: 0
        ));
    }

    #[Test]
    public function it_calculates_armor_class_with_medium_armor(): void
    {
        // Half Plate (15 base, max +2 DEX), DEX 14 (+2): AC = 15 + 2 = 17
        $this->assertEquals(17, $this->calculator->calculateAC(
            dexModifier: 2,
            armorBaseAC: 15,
            armorMaxDex: 2,
            shieldBonus: 0,
            otherBonuses: 0
        ));

        // Half Plate (15 base, max +2 DEX), DEX 18 (+4): AC = 15 + 2 = 17 (capped)
        $this->assertEquals(17, $this->calculator->calculateAC(
            dexModifier: 4,
            armorBaseAC: 15,
            armorMaxDex: 2,
            shieldBonus: 0,
            otherBonuses: 0
        ));
    }

    #[Test]
    public function it_calculates_armor_class_with_heavy_armor(): void
    {
        // Plate (18 base, no DEX), DEX 14 (+2): AC = 18 (DEX ignored)
        $this->assertEquals(18, $this->calculator->calculateAC(
            dexModifier: 2,
            armorBaseAC: 18,
            armorMaxDex: 0,
            shieldBonus: 0,
            otherBonuses: 0
        ));

        // Chain Mail (16 base, no DEX), DEX 18 (+4): AC = 16 (DEX ignored)
        $this->assertEquals(16, $this->calculator->calculateAC(
            dexModifier: 4,
            armorBaseAC: 16,
            armorMaxDex: 0,
            shieldBonus: 0,
            otherBonuses: 0
        ));
    }

    #[Test]
    public function it_calculates_armor_class_with_shield(): void
    {
        // Chain Mail (16 base, no DEX) + Shield (+2): AC = 18
        $this->assertEquals(18, $this->calculator->calculateAC(
            dexModifier: 2,
            armorBaseAC: 16,
            armorMaxDex: 0,
            shieldBonus: 2,
            otherBonuses: 0
        ));

        // Unarmored DEX 14 (+2) + Shield (+2): AC = 10 + 2 + 2 = 14
        $this->assertEquals(14, $this->calculator->calculateAC(
            dexModifier: 2,
            armorBaseAC: null,
            armorMaxDex: null,
            shieldBonus: 2,
            otherBonuses: 0
        ));
    }

    #[Test]
    public function it_calculates_armor_class_with_other_bonuses(): void
    {
        // Ring of Protection (+1), Cloak of Protection (+1) = +2 other
        // Leather (11) + DEX 14 (+2) + other (+2) = 15
        $this->assertEquals(15, $this->calculator->calculateAC(
            dexModifier: 2,
            armorBaseAC: 11,
            armorMaxDex: null,
            shieldBonus: 0,
            otherBonuses: 2
        ));
    }

    // ====================
    // Spell Slot Tests
    // ====================

    #[Test]
    public function it_calculates_wizard_spell_slots(): void
    {
        // Level 1 Wizard: 2 first-level slots
        $this->assertEquals([1 => 2], $this->calculator->getSpellSlots('wizard', 1));

        // Level 2 Wizard: 3 first-level slots
        $this->assertEquals([1 => 3], $this->calculator->getSpellSlots('wizard', 2));

        // Level 3 Wizard: 4 first, 2 second
        $this->assertEquals([1 => 4, 2 => 2], $this->calculator->getSpellSlots('wizard', 3));

        // Level 5 Wizard: 4 first, 3 second, 2 third
        $this->assertEquals([1 => 4, 2 => 3, 3 => 2], $this->calculator->getSpellSlots('wizard', 5));
    }

    #[Test]
    public function it_calculates_full_caster_spell_slots_at_high_levels(): void
    {
        // Level 9 full caster: gets 5th level slots
        $slots = $this->calculator->getSpellSlots('wizard', 9);
        $this->assertArrayHasKey(5, $slots);
        $this->assertEquals(1, $slots[5]);

        // Level 20 full caster: gets 9th level slots
        $slots = $this->calculator->getSpellSlots('wizard', 20);
        $this->assertArrayHasKey(9, $slots);
        $this->assertEquals(1, $slots[9]);
    }

    #[Test]
    public function it_calculates_paladin_spell_slots_as_half_caster(): void
    {
        // Level 2 Paladin: 2 first-level slots
        $this->assertEquals([1 => 2], $this->calculator->getSpellSlots('paladin', 2));

        // Level 5 Paladin: 4 first, 2 second
        $this->assertEquals([1 => 4, 2 => 2], $this->calculator->getSpellSlots('paladin', 5));
    }

    #[Test]
    public function it_returns_empty_slots_for_non_caster_or_level_1_paladin(): void
    {
        // Level 1 Paladin: no spellcasting yet
        $this->assertEquals([], $this->calculator->getSpellSlots('paladin', 1));

        // Fighter: no spellcasting
        $this->assertEquals([], $this->calculator->getSpellSlots('fighter', 5));
    }

    #[Test]
    public function it_calculates_warlock_pact_magic_slots(): void
    {
        // Level 1 Warlock: 1 first-level slot
        $this->assertEquals([1 => 1], $this->calculator->getSpellSlots('warlock', 1));

        // Level 2 Warlock: 2 first-level slots
        $this->assertEquals([1 => 2], $this->calculator->getSpellSlots('warlock', 2));

        // Level 5 Warlock: 2 third-level slots (Pact Magic)
        $this->assertEquals([3 => 2], $this->calculator->getSpellSlots('warlock', 5));
    }

    // ========================
    // Preparation Limit Tests
    // ========================

    #[Test]
    public function it_calculates_wizard_preparation_limit(): void
    {
        // Wizard: INT mod + level
        // Level 1, INT 16 (+3): 3 + 1 = 4
        $this->assertEquals(4, $this->calculator->getPreparationLimit(
            classSlug: 'wizard',
            level: 1,
            abilityModifier: 3
        ));

        // Level 5, INT 18 (+4): 4 + 5 = 9
        $this->assertEquals(9, $this->calculator->getPreparationLimit(
            classSlug: 'wizard',
            level: 5,
            abilityModifier: 4
        ));
    }

    #[Test]
    public function it_calculates_cleric_preparation_limit(): void
    {
        // Cleric: WIS mod + level
        // Level 3, WIS 16 (+3): 3 + 3 = 6
        $this->assertEquals(6, $this->calculator->getPreparationLimit(
            classSlug: 'cleric',
            level: 3,
            abilityModifier: 3
        ));
    }

    #[Test]
    public function it_calculates_paladin_preparation_limit(): void
    {
        // Paladin: CHA mod + half level (rounded down)
        // Level 2, CHA 16 (+3): 3 + 1 = 4
        $this->assertEquals(4, $this->calculator->getPreparationLimit(
            classSlug: 'paladin',
            level: 2,
            abilityModifier: 3
        ));

        // Level 5, CHA 16 (+3): 3 + 2 = 5
        $this->assertEquals(5, $this->calculator->getPreparationLimit(
            classSlug: 'paladin',
            level: 5,
            abilityModifier: 3
        ));
    }

    #[Test]
    public function it_returns_null_preparation_limit_for_known_casters(): void
    {
        // Sorcerer, Bard, Warlock don't prepare - they know a fixed number
        $this->assertNull($this->calculator->getPreparationLimit(
            classSlug: 'sorcerer',
            level: 5,
            abilityModifier: 4
        ));

        $this->assertNull($this->calculator->getPreparationLimit(
            classSlug: 'bard',
            level: 5,
            abilityModifier: 4
        ));

        $this->assertNull($this->calculator->getPreparationLimit(
            classSlug: 'warlock',
            level: 5,
            abilityModifier: 4
        ));
    }

    #[Test]
    public function it_ensures_minimum_1_prepared_spell(): void
    {
        // Even with negative modifier, should prepare at least 1
        $this->assertGreaterThanOrEqual(1, $this->calculator->getPreparationLimit(
            classSlug: 'wizard',
            level: 1,
            abilityModifier: -2
        ));
    }

    // ========================
    // Initiative Tests
    // ========================

    #[Test]
    public function it_calculates_initiative_from_dex_modifier(): void
    {
        // DEX 14 (+2): Initiative = +2
        $this->assertEquals(2, $this->calculator->calculateInitiative(2));

        // DEX 18 (+4): Initiative = +4
        $this->assertEquals(4, $this->calculator->calculateInitiative(4));

        // DEX 8 (-1): Initiative = -1
        $this->assertEquals(-1, $this->calculator->calculateInitiative(-1));
    }

    #[Test]
    public function it_calculates_initiative_with_bonus(): void
    {
        // DEX 14 (+2) with Alert feat (+5): Initiative = +7
        $this->assertEquals(7, $this->calculator->calculateInitiative(2, 5));

        // DEX 18 (+4) with other bonuses (+3): Initiative = +7
        $this->assertEquals(7, $this->calculator->calculateInitiative(4, 3));
    }

    // ========================
    // Passive Skill Tests
    // ========================

    #[Test]
    public function it_calculates_passive_skill_without_proficiency(): void
    {
        // WIS 14 (+2), not proficient: Passive = 10 + 2 = 12
        $this->assertEquals(12, $this->calculator->calculatePassiveSkill(
            abilityModifier: 2,
            proficient: false,
            expertise: false,
            proficiencyBonus: 2
        ));
    }

    #[Test]
    public function it_calculates_passive_skill_with_proficiency(): void
    {
        // WIS 14 (+2), proficient, level 1 (+2 prof): Passive = 10 + 2 + 2 = 14
        $this->assertEquals(14, $this->calculator->calculatePassiveSkill(
            abilityModifier: 2,
            proficient: true,
            expertise: false,
            proficiencyBonus: 2
        ));
    }

    #[Test]
    public function it_calculates_passive_skill_with_expertise(): void
    {
        // WIS 14 (+2), expertise, level 1 (+4 doubled prof): Passive = 10 + 2 + 4 = 16
        $this->assertEquals(16, $this->calculator->calculatePassiveSkill(
            abilityModifier: 2,
            proficient: true,
            expertise: true,
            proficiencyBonus: 2
        ));
    }

    #[Test]
    public function it_calculates_passive_skill_with_advantage(): void
    {
        // WIS 14 (+2), proficient (+2), advantage (+5): Passive = 10 + 4 + 5 = 19
        $this->assertEquals(19, $this->calculator->calculatePassiveSkill(
            abilityModifier: 2,
            proficient: true,
            expertise: false,
            proficiencyBonus: 2,
            advantageModifier: 5
        ));
    }

    #[Test]
    public function it_calculates_passive_skill_with_disadvantage(): void
    {
        // WIS 14 (+2), proficient (+2), disadvantage (-5): Passive = 10 + 4 - 5 = 9
        $this->assertEquals(9, $this->calculator->calculatePassiveSkill(
            abilityModifier: 2,
            proficient: true,
            expertise: false,
            proficiencyBonus: 2,
            advantageModifier: -5
        ));
    }

    // ========================
    // Carrying Capacity Tests
    // ========================

    #[Test]
    public function it_calculates_carrying_capacity_for_medium_creature(): void
    {
        // STR 10: 10 × 15 = 150 lbs
        $this->assertEquals(150, $this->calculator->calculateCarryingCapacity(10, 'Medium'));

        // STR 16: 16 × 15 = 240 lbs
        $this->assertEquals(240, $this->calculator->calculateCarryingCapacity(16, 'Medium'));

        // STR 20: 20 × 15 = 300 lbs
        $this->assertEquals(300, $this->calculator->calculateCarryingCapacity(20, 'Medium'));
    }

    #[Test]
    public function it_calculates_carrying_capacity_for_small_creature(): void
    {
        // STR 10, Small: 10 × 15 × 1 = 150 lbs (same as Medium)
        $this->assertEquals(150, $this->calculator->calculateCarryingCapacity(10, 'Small'));
    }

    #[Test]
    public function it_calculates_carrying_capacity_for_tiny_creature(): void
    {
        // STR 10, Tiny: 10 × 15 × 0.5 = 75 lbs
        $this->assertEquals(75, $this->calculator->calculateCarryingCapacity(10, 'Tiny'));
    }

    #[Test]
    public function it_calculates_carrying_capacity_for_large_creature(): void
    {
        // STR 10, Large: 10 × 15 × 2 = 300 lbs
        $this->assertEquals(300, $this->calculator->calculateCarryingCapacity(10, 'Large'));
    }

    #[Test]
    public function it_calculates_carrying_capacity_for_huge_creature(): void
    {
        // STR 10, Huge: 10 × 15 × 4 = 600 lbs
        $this->assertEquals(600, $this->calculator->calculateCarryingCapacity(10, 'Huge'));
    }

    #[Test]
    public function it_calculates_carrying_capacity_for_gargantuan_creature(): void
    {
        // STR 10, Gargantuan: 10 × 15 × 8 = 1200 lbs
        $this->assertEquals(1200, $this->calculator->calculateCarryingCapacity(10, 'Gargantuan'));
    }

    #[Test]
    public function it_calculates_push_drag_lift_as_double_carrying_capacity(): void
    {
        // STR 10, Medium: 150 × 2 = 300 lbs
        $this->assertEquals(300, $this->calculator->calculatePushDragLift(10, 'Medium'));

        // STR 16, Medium: 240 × 2 = 480 lbs
        $this->assertEquals(480, $this->calculator->calculatePushDragLift(16, 'Medium'));

        // STR 10, Large: 300 × 2 = 600 lbs
        $this->assertEquals(600, $this->calculator->calculatePushDragLift(10, 'Large'));
    }

    #[Test]
    public function it_handles_case_insensitive_size(): void
    {
        // Should work with various case formats
        $this->assertEquals(150, $this->calculator->calculateCarryingCapacity(10, 'medium'));
        $this->assertEquals(150, $this->calculator->calculateCarryingCapacity(10, 'MEDIUM'));
        $this->assertEquals(300, $this->calculator->calculateCarryingCapacity(10, 'LARGE'));
    }
}
