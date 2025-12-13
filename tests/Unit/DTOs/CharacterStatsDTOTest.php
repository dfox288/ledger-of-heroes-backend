<?php

namespace Tests\Unit\DTOs;

use App\DTOs\CharacterStatsDTO;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\Race;
use App\Models\Skill;
use App\Services\CharacterStatCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for CharacterStatsDTO - Issue #255: Enhanced Stats Endpoint
 */
class CharacterStatsDTOTest extends TestCase
{
    use RefreshDatabase;

    private CharacterStatCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = app(CharacterStatCalculator::class);
    }

    // === Saving Throw Proficiency Tests ===

    #[Test]
    public function it_includes_saving_throw_proficiency_status_from_primary_class(): void
    {
        // Fighter has STR and CON saving throw proficiencies
        $fighter = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter',
        ]);

        // Add saving throw proficiencies to the fighter class
        $fighter->proficiencies()->createMany([
            ['proficiency_type' => 'saving_throw', 'proficiency_name' => 'Strength'],
            ['proficiency_type' => 'saving_throw', 'proficiency_name' => 'Constitution'],
        ]);

        $character = Character::factory()->create([
            'strength' => 14,
            'dexterity' => 12,
            'constitution' => 14,
        ]);

        $character->characterClasses()->create([
            'class_slug' => $fighter->slug,
            'level' => 1,
            'order' => 1,
            'is_primary' => true,
        ]);

        $dto = CharacterStatsDTO::fromCharacter($character->fresh(), $this->calculator);

        // Saving throws should be arrays with proficiency info
        $this->assertIsArray($dto->savingThrows['STR']);
        $this->assertTrue($dto->savingThrows['STR']['proficient']);
        $this->assertTrue($dto->savingThrows['CON']['proficient']);
        $this->assertFalse($dto->savingThrows['DEX']['proficient']);
        $this->assertFalse($dto->savingThrows['INT']['proficient']);
        $this->assertFalse($dto->savingThrows['WIS']['proficient']);
        $this->assertFalse($dto->savingThrows['CHA']['proficient']);
    }

    #[Test]
    public function it_calculates_saving_throw_total_with_proficiency_bonus(): void
    {
        $fighter = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter',
        ]);

        $fighter->proficiencies()->createMany([
            ['proficiency_type' => 'saving_throw', 'proficiency_name' => 'Strength'],
            ['proficiency_type' => 'saving_throw', 'proficiency_name' => 'Constitution'],
        ]);

        $character = Character::factory()->create([
            'strength' => 16, // +3 modifier
            'dexterity' => 14, // +2 modifier
        ]);

        $character->characterClasses()->create([
            'class_slug' => $fighter->slug,
            'level' => 5, // +3 proficiency bonus
            'order' => 1,
            'is_primary' => true,
        ]);

        $dto = CharacterStatsDTO::fromCharacter($character->fresh(), $this->calculator);

        // STR save: +3 (mod) + 3 (prof) = +6
        $this->assertEquals(3, $dto->savingThrows['STR']['modifier']);
        $this->assertTrue($dto->savingThrows['STR']['proficient']);
        $this->assertEquals(6, $dto->savingThrows['STR']['total']);

        // DEX save: +2 (mod only, no proficiency)
        $this->assertEquals(2, $dto->savingThrows['DEX']['modifier']);
        $this->assertFalse($dto->savingThrows['DEX']['proficient']);
        $this->assertEquals(2, $dto->savingThrows['DEX']['total']);
    }

    #[Test]
    public function it_handles_character_without_class_gracefully(): void
    {
        $character = Character::factory()->create([
            'strength' => 14, // +2 modifier
        ]);

        $dto = CharacterStatsDTO::fromCharacter($character, $this->calculator);

        // No class = no proficiencies, but structure should still exist
        $this->assertIsArray($dto->savingThrows['STR']);
        $this->assertFalse($dto->savingThrows['STR']['proficient']);
        $this->assertEquals(2, $dto->savingThrows['STR']['modifier']);
        $this->assertEquals(2, $dto->savingThrows['STR']['total']);
    }

    // === Skills Array Tests ===

    #[Test]
    public function it_returns_all_18_skills_with_correct_structure(): void
    {
        $character = Character::factory()->create();

        $dto = CharacterStatsDTO::fromCharacter($character, $this->calculator);

        $this->assertCount(18, $dto->skills);
        $this->assertArrayHasKey('name', $dto->skills[0]);
        $this->assertArrayHasKey('slug', $dto->skills[0]);
        $this->assertArrayHasKey('ability', $dto->skills[0]);
        $this->assertArrayHasKey('ability_modifier', $dto->skills[0]);
        $this->assertArrayHasKey('proficient', $dto->skills[0]);
        $this->assertArrayHasKey('expertise', $dto->skills[0]);
        $this->assertArrayHasKey('modifier', $dto->skills[0]);
        $this->assertArrayHasKey('passive', $dto->skills[0]);
    }

    #[Test]
    public function it_calculates_skill_modifier_with_proficiency(): void
    {
        $character = Character::factory()->create(['dexterity' => 16]); // +3 mod

        // Add Stealth proficiency
        $stealthSkill = Skill::where('slug', 'core:stealth')->first();
        $this->assertNotNull($stealthSkill, 'Stealth skill must exist');

        $character->proficiencies()->create([
            'skill_slug' => $stealthSkill->slug,
            'expertise' => false,
        ]);

        // Need to set level for proficiency bonus
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'slug' => 'fighter']);
        $character->characterClasses()->create([
            'class_slug' => $fighter->slug,
            'level' => 1, // +2 proficiency bonus
            'order' => 1,
            'is_primary' => true,
        ]);

        $dto = CharacterStatsDTO::fromCharacter(
            $character->fresh()->load('proficiencies.skill'),
            $this->calculator
        );

        $stealth = collect($dto->skills)->firstWhere('slug', 'core:stealth');

        $this->assertTrue($stealth['proficient']);
        $this->assertFalse($stealth['expertise']);
        $this->assertEquals('DEX', $stealth['ability']);
        $this->assertEquals(3, $stealth['ability_modifier']);
        $this->assertEquals(5, $stealth['modifier']); // +3 DEX + 2 prof
    }

    #[Test]
    public function it_calculates_skill_modifier_with_expertise(): void
    {
        $character = Character::factory()->create(['dexterity' => 16]); // +3 mod

        // Add Stealth expertise
        $stealthSkill = Skill::where('slug', 'core:stealth')->first();
        $character->proficiencies()->create([
            'skill_slug' => $stealthSkill->slug,
            'expertise' => true,
        ]);

        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'slug' => 'fighter']);
        $character->characterClasses()->create([
            'class_slug' => $fighter->slug,
            'level' => 1, // +2 proficiency bonus
            'order' => 1,
            'is_primary' => true,
        ]);

        $dto = CharacterStatsDTO::fromCharacter(
            $character->fresh()->load('proficiencies.skill'),
            $this->calculator
        );

        $stealth = collect($dto->skills)->firstWhere('slug', 'core:stealth');

        $this->assertTrue($stealth['proficient']);
        $this->assertTrue($stealth['expertise']);
        $this->assertEquals(7, $stealth['modifier']); // +3 DEX + 4 expertise (2x prof)
    }

    #[Test]
    public function it_includes_passive_score_for_each_skill(): void
    {
        $character = Character::factory()->create(['wisdom' => 14]); // +2 mod

        $dto = CharacterStatsDTO::fromCharacter($character, $this->calculator);

        $perception = collect($dto->skills)->firstWhere('slug', 'core:perception');

        $this->assertEquals(12, $perception['passive']); // 10 + 2 WIS mod
    }

    // === Speed Tests ===

    #[Test]
    public function it_returns_speed_array_with_all_movement_types(): void
    {
        $character = Character::factory()->create();

        $dto = CharacterStatsDTO::fromCharacter($character, $this->calculator);

        $this->assertIsArray($dto->speed);
        $this->assertArrayHasKey('walk', $dto->speed);
        $this->assertArrayHasKey('fly', $dto->speed);
        $this->assertArrayHasKey('swim', $dto->speed);
        $this->assertArrayHasKey('climb', $dto->speed);
        $this->assertArrayHasKey('burrow', $dto->speed);
    }

    #[Test]
    public function it_defaults_walk_speed_to_30(): void
    {
        $character = Character::factory()->create();

        $dto = CharacterStatsDTO::fromCharacter($character, $this->calculator);

        // Default speed is 30 when no race speed is set
        $this->assertEquals(30, $dto->speed['walk']);
    }

    #[Test]
    public function it_uses_race_speed_when_available(): void
    {
        $race = Race::factory()->create([
            'speed' => 25,
            'fly_speed' => 50,
            'swim_speed' => 30,
            'climb_speed' => 20,
        ]);
        $character = Character::factory()->create(['race_slug' => $race->slug]);

        $dto = CharacterStatsDTO::fromCharacter($character, $this->calculator);

        $this->assertEquals(25, $dto->speed['walk']);
        $this->assertEquals(50, $dto->speed['fly']);
        $this->assertEquals(30, $dto->speed['swim']);
        $this->assertEquals(20, $dto->speed['climb']);
    }

    // === Passive Scores Tests ===

    #[Test]
    public function it_returns_grouped_passive_scores_object(): void
    {
        $character = Character::factory()->create([
            'wisdom' => 14,      // +2 mod -> passive 12
            'intelligence' => 16, // +3 mod -> passive 13
        ]);

        $dto = CharacterStatsDTO::fromCharacter($character, $this->calculator);

        $this->assertIsArray($dto->passive);
        $this->assertArrayHasKey('perception', $dto->passive);
        $this->assertArrayHasKey('investigation', $dto->passive);
        $this->assertArrayHasKey('insight', $dto->passive);
        $this->assertEquals(12, $dto->passive['perception']);
        $this->assertEquals(13, $dto->passive['investigation']);
        $this->assertEquals(12, $dto->passive['insight']);
    }

    // === Resilient Feat Saving Throw Tests (Issue #497) ===

    #[Test]
    public function it_includes_saving_throw_proficiency_from_resilient_feat(): void
    {
        // Create a character with a class that has STR/CON saves
        $fighter = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'test:fighter',
        ]);
        $fighter->proficiencies()->createMany([
            ['proficiency_type' => 'saving_throw', 'proficiency_name' => 'Strength'],
            ['proficiency_type' => 'saving_throw', 'proficiency_name' => 'Constitution'],
        ]);

        $character = Character::factory()->create([
            'strength' => 14,
            'wisdom' => 16, // +3 modifier
        ]);
        $character->characterClasses()->create([
            'class_slug' => $fighter->slug,
            'level' => 5, // +3 proficiency bonus
            'order' => 1,
            'is_primary' => true,
        ]);

        // Add Resilient (Wisdom) feat
        $resilientWis = \App\Models\Feat::factory()->create([
            'name' => 'Resilient (Wisdom)',
            'slug' => 'test:resilient-wisdom',
        ]);
        $character->features()->create([
            'feature_type' => \App\Models\Feat::class,
            'feature_id' => $resilientWis->id,
            'feature_slug' => $resilientWis->slug,
            'source' => 'asi_or_feat',
        ]);

        $dto = CharacterStatsDTO::fromCharacter($character->fresh(), $this->calculator);

        // Fighter class gives STR and CON proficiency
        $this->assertTrue($dto->savingThrows['STR']['proficient']);
        $this->assertTrue($dto->savingThrows['CON']['proficient']);

        // Resilient feat gives WIS proficiency
        $this->assertTrue($dto->savingThrows['WIS']['proficient']);
        // WIS save: +3 (mod) + 3 (prof) = +6
        $this->assertEquals(6, $dto->savingThrows['WIS']['total']);

        // Other saves remain unproficient
        $this->assertFalse($dto->savingThrows['DEX']['proficient']);
        $this->assertFalse($dto->savingThrows['INT']['proficient']);
        $this->assertFalse($dto->savingThrows['CHA']['proficient']);
    }

    #[Test]
    public function it_handles_multiple_resilient_feats(): void
    {
        $character = Character::factory()->create([
            'dexterity' => 14, // +2 modifier
            'wisdom' => 12,    // +1 modifier
        ]);

        // Add both Resilient (Dexterity) and Resilient (Wisdom)
        $resilientDex = \App\Models\Feat::factory()->create([
            'name' => 'Resilient (Dexterity)',
            'slug' => 'test:resilient-dexterity',
        ]);
        $resilientWis = \App\Models\Feat::factory()->create([
            'name' => 'Resilient (Wisdom)',
            'slug' => 'test:resilient-wisdom',
        ]);

        $character->features()->createMany([
            [
                'feature_type' => \App\Models\Feat::class,
                'feature_id' => $resilientDex->id,
                'feature_slug' => $resilientDex->slug,
                'source' => 'asi_or_feat',
            ],
            [
                'feature_type' => \App\Models\Feat::class,
                'feature_id' => $resilientWis->id,
                'feature_slug' => $resilientWis->slug,
                'source' => 'asi_or_feat',
            ],
        ]);

        $dto = CharacterStatsDTO::fromCharacter($character->fresh(), $this->calculator);

        $this->assertTrue($dto->savingThrows['DEX']['proficient']);
        $this->assertTrue($dto->savingThrows['WIS']['proficient']);
    }

    // === Fighting Style Tests (Issue #497) ===

    #[Test]
    public function it_exposes_fighting_styles_the_character_has(): void
    {
        $fighter = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'test:fighter-fs',
        ]);

        // Create fighting style feature
        $archeryStyle = \App\Models\ClassFeature::factory()->create([
            'class_id' => $fighter->id,
            'feature_name' => 'Fighting Style: Archery',
            'level' => 1,
        ]);

        $character = Character::factory()->create();
        $character->characterClasses()->create([
            'class_slug' => $fighter->slug,
            'level' => 1,
            'order' => 1,
            'is_primary' => true,
        ]);

        // Assign the fighting style to the character
        $character->features()->create([
            'feature_type' => \App\Models\ClassFeature::class,
            'feature_id' => $archeryStyle->id,
            'feature_slug' => 'test:fighter-fs:fighting-style-archery',
            'source' => 'class',
        ]);

        $dto = CharacterStatsDTO::fromCharacter($character->fresh(), $this->calculator);

        $this->assertIsArray($dto->fightingStyles);
        $this->assertContains('Archery', $dto->fightingStyles);
    }

    #[Test]
    public function it_includes_ranged_attack_bonus_from_archery_style(): void
    {
        $fighter = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'test:fighter-archery',
        ]);

        $archeryStyle = \App\Models\ClassFeature::factory()->create([
            'class_id' => $fighter->id,
            'feature_name' => 'Fighting Style: Archery',
            'level' => 1,
        ]);

        $character = Character::factory()->create();
        $character->characterClasses()->create([
            'class_slug' => $fighter->slug,
            'level' => 1,
            'order' => 1,
            'is_primary' => true,
        ]);

        $character->features()->create([
            'feature_type' => \App\Models\ClassFeature::class,
            'feature_id' => $archeryStyle->id,
            'feature_slug' => 'test:fighter-archery:fighting-style-archery',
            'source' => 'class',
        ]);

        $dto = CharacterStatsDTO::fromCharacter($character->fresh(), $this->calculator);

        $this->assertEquals(2, $dto->rangedAttackBonus);
    }

    #[Test]
    public function it_includes_melee_damage_bonus_from_dueling_style(): void
    {
        $fighter = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'test:fighter-dueling',
        ]);

        $duelingStyle = \App\Models\ClassFeature::factory()->create([
            'class_id' => $fighter->id,
            'feature_name' => 'Fighting Style: Dueling',
            'level' => 1,
        ]);

        $character = Character::factory()->create();
        $character->characterClasses()->create([
            'class_slug' => $fighter->slug,
            'level' => 1,
            'order' => 1,
            'is_primary' => true,
        ]);

        $character->features()->create([
            'feature_type' => \App\Models\ClassFeature::class,
            'feature_id' => $duelingStyle->id,
            'feature_slug' => 'test:fighter-dueling:fighting-style-dueling',
            'source' => 'class',
        ]);

        $dto = CharacterStatsDTO::fromCharacter($character->fresh(), $this->calculator);

        $this->assertEquals(2, $dto->meleeDamageBonus);
    }

    // === Jack of All Trades Tests (Issue #497.2.1) ===

    #[Test]
    public function it_applies_half_proficiency_to_non_proficient_skills_with_jack_of_all_trades(): void
    {
        // Create Bard class
        $bard = CharacterClass::factory()->create([
            'name' => 'Bard',
            'slug' => 'test:bard-jat',
        ]);

        // Create Jack of All Trades feature
        $jackOfAllTrades = \App\Models\ClassFeature::factory()->create([
            'class_id' => $bard->id,
            'feature_name' => 'Jack of All Trades',
            'level' => 2,
        ]);

        // Character with DEX 14 (+2 mod)
        $character = Character::factory()->create([
            'dexterity' => 14, // +2 mod
        ]);

        // Level 5 Bard (+3 proficiency bonus, so half = +1)
        $character->characterClasses()->create([
            'class_slug' => $bard->slug,
            'level' => 5,
            'order' => 1,
            'is_primary' => true,
        ]);

        // Assign Jack of All Trades feature
        $character->features()->create([
            'feature_type' => \App\Models\ClassFeature::class,
            'feature_id' => $jackOfAllTrades->id,
            'feature_slug' => 'test:bard-jat:jack-of-all-trades',
            'source' => 'class',
        ]);

        $dto = CharacterStatsDTO::fromCharacter($character->fresh(), $this->calculator);

        // Stealth (DEX) - not proficient, should get half proficiency bonus
        $stealth = collect($dto->skills)->firstWhere('slug', 'core:stealth');

        $this->assertFalse($stealth['proficient']);
        // DEX mod (+2) + half prof bonus (floor(3/2) = +1) = +3
        $this->assertEquals(3, $stealth['modifier']);
    }

    #[Test]
    public function it_does_not_apply_jack_of_all_trades_to_proficient_skills(): void
    {
        $bard = CharacterClass::factory()->create([
            'name' => 'Bard',
            'slug' => 'test:bard-jat-prof',
        ]);

        $jackOfAllTrades = \App\Models\ClassFeature::factory()->create([
            'class_id' => $bard->id,
            'feature_name' => 'Jack of All Trades',
            'level' => 2,
        ]);

        $character = Character::factory()->create([
            'dexterity' => 14, // +2 mod
        ]);

        $character->characterClasses()->create([
            'class_slug' => $bard->slug,
            'level' => 5, // +3 proficiency bonus
            'order' => 1,
            'is_primary' => true,
        ]);

        // Add Stealth proficiency
        $stealthSkill = Skill::where('slug', 'core:stealth')->first();
        $character->proficiencies()->create([
            'skill_slug' => $stealthSkill->slug,
            'expertise' => false,
        ]);

        $character->features()->create([
            'feature_type' => \App\Models\ClassFeature::class,
            'feature_id' => $jackOfAllTrades->id,
            'feature_slug' => 'test:bard-jat-prof:jack-of-all-trades',
            'source' => 'class',
        ]);

        $dto = CharacterStatsDTO::fromCharacter($character->fresh()->load('proficiencies.skill'), $this->calculator);

        // Stealth (DEX) - proficient, gets full proficiency bonus (not half)
        $stealth = collect($dto->skills)->firstWhere('slug', 'core:stealth');

        $this->assertTrue($stealth['proficient']);
        // DEX mod (+2) + full prof bonus (+3) = +5
        $this->assertEquals(5, $stealth['modifier']);
    }

    #[Test]
    public function it_applies_jack_of_all_trades_to_initiative(): void
    {
        $bard = CharacterClass::factory()->create([
            'name' => 'Bard',
            'slug' => 'test:bard-jat-init',
        ]);

        $jackOfAllTrades = \App\Models\ClassFeature::factory()->create([
            'class_id' => $bard->id,
            'feature_name' => 'Jack of All Trades',
            'level' => 2,
        ]);

        $character = Character::factory()->create([
            'dexterity' => 14, // +2 mod
        ]);

        $character->characterClasses()->create([
            'class_slug' => $bard->slug,
            'level' => 5, // +3 proficiency bonus
            'order' => 1,
            'is_primary' => true,
        ]);

        $character->features()->create([
            'feature_type' => \App\Models\ClassFeature::class,
            'feature_id' => $jackOfAllTrades->id,
            'feature_slug' => 'test:bard-jat-init:jack-of-all-trades',
            'source' => 'class',
        ]);

        $dto = CharacterStatsDTO::fromCharacter($character->fresh(), $this->calculator);

        // Initiative should include half proficiency bonus
        // DEX mod (+2) + half prof bonus (floor(3/2) = +1) = +3
        $this->assertEquals(3, $dto->initiativeBonus);
    }

    // === Reliable Talent Tests (Issue #497.2.2) ===

    #[Test]
    public function it_flags_proficient_skills_with_reliable_talent(): void
    {
        $rogue = CharacterClass::factory()->create([
            'name' => 'Rogue',
            'slug' => 'test:rogue-rt',
        ]);

        $reliableTalent = \App\Models\ClassFeature::factory()->create([
            'class_id' => $rogue->id,
            'feature_name' => 'Reliable Talent',
            'level' => 11,
        ]);

        $character = Character::factory()->create([
            'dexterity' => 16, // +3 mod
        ]);

        $character->characterClasses()->create([
            'class_slug' => $rogue->slug,
            'level' => 11,
            'order' => 1,
            'is_primary' => true,
        ]);

        // Add Stealth proficiency
        $stealthSkill = Skill::where('slug', 'core:stealth')->first();
        $character->proficiencies()->create([
            'skill_slug' => $stealthSkill->slug,
            'expertise' => false,
        ]);

        $character->features()->create([
            'feature_type' => \App\Models\ClassFeature::class,
            'feature_id' => $reliableTalent->id,
            'feature_slug' => 'test:rogue-rt:reliable-talent',
            'source' => 'class',
        ]);

        $dto = CharacterStatsDTO::fromCharacter($character->fresh()->load('proficiencies.skill'), $this->calculator);

        // Stealth (proficient) should have reliable_talent flag
        $stealth = collect($dto->skills)->firstWhere('slug', 'core:stealth');
        $this->assertTrue($stealth['proficient']);
        $this->assertTrue($stealth['has_reliable_talent']);
    }

    #[Test]
    public function it_does_not_flag_non_proficient_skills_with_reliable_talent(): void
    {
        $rogue = CharacterClass::factory()->create([
            'name' => 'Rogue',
            'slug' => 'test:rogue-rt-np',
        ]);

        $reliableTalent = \App\Models\ClassFeature::factory()->create([
            'class_id' => $rogue->id,
            'feature_name' => 'Reliable Talent',
            'level' => 11,
        ]);

        $character = Character::factory()->create([
            'dexterity' => 16,
        ]);

        $character->characterClasses()->create([
            'class_slug' => $rogue->slug,
            'level' => 11,
            'order' => 1,
            'is_primary' => true,
        ]);

        $character->features()->create([
            'feature_type' => \App\Models\ClassFeature::class,
            'feature_id' => $reliableTalent->id,
            'feature_slug' => 'test:rogue-rt-np:reliable-talent',
            'source' => 'class',
        ]);

        $dto = CharacterStatsDTO::fromCharacter($character->fresh(), $this->calculator);

        // Stealth (not proficient) should NOT have reliable_talent flag
        $stealth = collect($dto->skills)->firstWhere('slug', 'core:stealth');
        $this->assertFalse($stealth['proficient']);
        $this->assertFalse($stealth['has_reliable_talent']);
    }

    #[Test]
    public function it_includes_has_reliable_talent_in_skill_structure(): void
    {
        $character = Character::factory()->create();

        $dto = CharacterStatsDTO::fromCharacter($character, $this->calculator);

        // Every skill should have the has_reliable_talent key
        $this->assertArrayHasKey('has_reliable_talent', $dto->skills[0]);
    }
}
