<?php

namespace Tests\Unit\DTOs;

use App\DTOs\CharacterStatsDTO;
use App\Models\Character;
use App\Models\CharacterClass;
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
            'class_id' => $fighter->id,
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
            'class_id' => $fighter->id,
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
        $stealthSkill = Skill::where('slug', 'stealth')->first();
        $this->assertNotNull($stealthSkill, 'Stealth skill must exist');

        $character->proficiencies()->create([
            'skill_id' => $stealthSkill->id,
            'expertise' => false,
        ]);

        // Need to set level for proficiency bonus
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'slug' => 'fighter']);
        $character->characterClasses()->create([
            'class_id' => $fighter->id,
            'level' => 1, // +2 proficiency bonus
            'order' => 1,
            'is_primary' => true,
        ]);

        $dto = CharacterStatsDTO::fromCharacter(
            $character->fresh()->load('proficiencies.skill'),
            $this->calculator
        );

        $stealth = collect($dto->skills)->firstWhere('slug', 'stealth');

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
        $stealthSkill = Skill::where('slug', 'stealth')->first();
        $character->proficiencies()->create([
            'skill_id' => $stealthSkill->id,
            'expertise' => true,
        ]);

        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'slug' => 'fighter']);
        $character->characterClasses()->create([
            'class_id' => $fighter->id,
            'level' => 1, // +2 proficiency bonus
            'order' => 1,
            'is_primary' => true,
        ]);

        $dto = CharacterStatsDTO::fromCharacter(
            $character->fresh()->load('proficiencies.skill'),
            $this->calculator
        );

        $stealth = collect($dto->skills)->firstWhere('slug', 'stealth');

        $this->assertTrue($stealth['proficient']);
        $this->assertTrue($stealth['expertise']);
        $this->assertEquals(7, $stealth['modifier']); // +3 DEX + 4 expertise (2x prof)
    }

    #[Test]
    public function it_includes_passive_score_for_each_skill(): void
    {
        $character = Character::factory()->create(['wisdom' => 14]); // +2 mod

        $dto = CharacterStatsDTO::fromCharacter($character, $this->calculator);

        $perception = collect($dto->skills)->firstWhere('slug', 'perception');

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
}
