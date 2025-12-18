<?php

namespace Tests\Unit\DTOs;

use App\DTOs\CharacterStatsDTO;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Services\CharacterStatCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for unarmed strike and improvised weapon calculations in CharacterStatsDTO.
 *
 * Issue #751: Backend-calculated combat data for battle page.
 *
 * D&D 5e Unarmed Strike Rules (PHB p.195):
 * - Base damage: 1 + STR modifier (flat damage, no dice)
 * - Attack roll: d20 + STR modifier + proficiency bonus (everyone is proficient)
 * - Damage type: bludgeoning
 *
 * D&D 5e Improvised Weapon Rules (PHB p.147-148):
 * - Damage: 1d4 + ability modifier
 * - Attack roll: d20 + ability modifier (NO proficiency by default)
 * - Damage type: DM assigns based on object
 */
class CharacterStatsDTOUnarmedStrikeTest extends TestCase
{
    use RefreshDatabase;

    private CharacterStatCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = app(CharacterStatCalculator::class);
    }

    // === Basic Unarmed Strike ===

    #[Test]
    public function it_provides_basic_unarmed_strike_for_any_character(): void
    {
        // STR 14 (+2), level 1 (+2 prof)
        $character = Character::factory()->create([
            'strength' => 14,
            'dexterity' => 10,
        ]);

        // Add a class to establish level and proficiency
        $fighter = CharacterClass::factory()->create(['slug' => 'test:fighter-unarmed']);
        $character->characterClasses()->create([
            'class_slug' => $fighter->slug,
            'level' => 1,
            'order' => 1,
            'is_primary' => true,
        ]);

        $dto = CharacterStatsDTO::fromCharacter(
            $character->fresh(),
            $this->calculator
        );

        $this->assertNotNull($dto->unarmedStrike);
        $this->assertEquals('Unarmed Strike', $dto->unarmedStrike['name']);
        // Attack = STR mod (+2) + proficiency (+2) = +4
        $this->assertEquals(4, $dto->unarmedStrike['attack_bonus']);
        // Damage bonus = STR mod (+2) - base 1 damage is implicit
        $this->assertEquals(2, $dto->unarmedStrike['damage_bonus']);
        // No dice for basic unarmed strike
        $this->assertNull($dto->unarmedStrike['damage_dice']);
        $this->assertEquals('bludgeoning', $dto->unarmedStrike['damage_type']);
        $this->assertEquals('STR', $dto->unarmedStrike['ability_used']);
        // No special source for basic unarmed strike
        $this->assertNull($dto->unarmedStrike['source']);
    }

    #[Test]
    public function it_scales_attack_bonus_with_level(): void
    {
        // STR 16 (+3), level 5 (+3 prof)
        $character = Character::factory()->create([
            'strength' => 16,
            'dexterity' => 10,
        ]);

        $fighter = CharacterClass::factory()->create(['slug' => 'test:fighter-unarmed-l5']);
        $character->characterClasses()->create([
            'class_slug' => $fighter->slug,
            'level' => 5,
            'order' => 1,
            'is_primary' => true,
        ]);

        $dto = CharacterStatsDTO::fromCharacter(
            $character->fresh(),
            $this->calculator
        );

        // Attack = STR mod (+3) + proficiency (+3) = +6
        $this->assertEquals(6, $dto->unarmedStrike['attack_bonus']);
        // Damage = STR mod (+3)
        $this->assertEquals(3, $dto->unarmedStrike['damage_bonus']);
    }

    #[Test]
    public function it_handles_negative_strength_modifier(): void
    {
        // STR 8 (-1), level 1 (+2 prof)
        $character = Character::factory()->create([
            'strength' => 8,
            'dexterity' => 14,
        ]);

        $commoner = CharacterClass::factory()->create(['slug' => 'test:commoner-unarmed']);
        $character->characterClasses()->create([
            'class_slug' => $commoner->slug,
            'level' => 1,
            'order' => 1,
            'is_primary' => true,
        ]);

        $dto = CharacterStatsDTO::fromCharacter(
            $character->fresh(),
            $this->calculator
        );

        // Attack = STR mod (-1) + proficiency (+2) = +1
        $this->assertEquals(1, $dto->unarmedStrike['attack_bonus']);
        // Damage = STR mod (-1) - minimum damage would be 0 (1 base + -1 mod)
        $this->assertEquals(-1, $dto->unarmedStrike['damage_bonus']);
        $this->assertEquals('STR', $dto->unarmedStrike['ability_used']);
    }

    #[Test]
    public function it_handles_character_without_class(): void
    {
        // Character with no class (draft character)
        $character = Character::factory()->create([
            'strength' => 12,
            'dexterity' => 10,
        ]);

        $dto = CharacterStatsDTO::fromCharacter(
            $character->fresh(),
            $this->calculator
        );

        // Should still provide unarmed strike
        // Level 0 proficiency: 2 + floor(-1/4) = +1
        $this->assertNotNull($dto->unarmedStrike);
        // Attack = STR mod (+1) + proficiency (+1) = +2
        $this->assertEquals(2, $dto->unarmedStrike['attack_bonus']);
        $this->assertEquals(1, $dto->unarmedStrike['damage_bonus']);
    }

    // === Improvised Weapon ===

    #[Test]
    public function it_provides_improvised_weapon_stats(): void
    {
        // STR 14 (+2), level 5 (+3 prof) - but prof doesn't apply to improvised
        $character = Character::factory()->create([
            'strength' => 14,
            'dexterity' => 10,
        ]);

        $fighter = CharacterClass::factory()->create(['slug' => 'test:fighter-improv']);
        $character->characterClasses()->create([
            'class_slug' => $fighter->slug,
            'level' => 5,
            'order' => 1,
            'is_primary' => true,
        ]);

        $dto = CharacterStatsDTO::fromCharacter(
            $character->fresh(),
            $this->calculator
        );

        $this->assertNotNull($dto->improvisedWeapon);
        $this->assertEquals('Improvised Weapon', $dto->improvisedWeapon['name']);
        // Attack = STR mod (+2) only - NO proficiency for improvised weapons
        $this->assertEquals(2, $dto->improvisedWeapon['attack_bonus']);
        // Damage = 1d4 + STR mod
        $this->assertEquals('1d4', $dto->improvisedWeapon['damage_dice']);
        $this->assertEquals(2, $dto->improvisedWeapon['damage_bonus']);
        // Damage type is null - DM determines based on object
        $this->assertNull($dto->improvisedWeapon['damage_type']);
        $this->assertEquals('STR', $dto->improvisedWeapon['ability_used']);
        $this->assertNull($dto->improvisedWeapon['source']);
    }

    #[Test]
    public function improvised_weapon_does_not_include_proficiency(): void
    {
        // STR 16 (+3), level 9 (+4 prof) - prof should NOT apply
        $character = Character::factory()->create([
            'strength' => 16,
            'dexterity' => 10,
        ]);

        $fighter = CharacterClass::factory()->create(['slug' => 'test:fighter-improv-l9']);
        $character->characterClasses()->create([
            'class_slug' => $fighter->slug,
            'level' => 9,
            'order' => 1,
            'is_primary' => true,
        ]);

        $dto = CharacterStatsDTO::fromCharacter(
            $character->fresh(),
            $this->calculator
        );

        // Attack = STR mod (+3) only - proficiency (+4) should NOT be added
        $this->assertEquals(3, $dto->improvisedWeapon['attack_bonus']);
        // Compare to unarmed which DOES include proficiency
        $this->assertEquals(7, $dto->unarmedStrike['attack_bonus']); // +3 STR + +4 prof
    }
}
