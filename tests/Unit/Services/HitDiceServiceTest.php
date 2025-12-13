<?php

namespace Tests\Unit\Services;

use App\Exceptions\InsufficientHitDiceException;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use App\Services\HitDiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HitDiceServiceTest extends TestCase
{
    use RefreshDatabase;

    private HitDiceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new HitDiceService;
    }

    #[Test]
    public function it_returns_hit_dice_for_single_class_character(): void
    {
        // Arrange: Create a level 5 Fighter (d10)
        $fighterClass = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'hit_die' => 10,
            'parent_class_id' => null,
        ]);

        $character = Character::factory()->create();
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $fighterClass->slug,
            'level' => 5,
            'hit_dice_spent' => 2,
            'is_primary' => true,
        ]);

        // Act
        $result = $this->service->getHitDice($character);

        // Assert
        $this->assertArrayHasKey('hit_dice', $result);
        $this->assertArrayHasKey('total', $result);

        $this->assertEquals([
            'd10' => ['available' => 3, 'max' => 5, 'spent' => 2],
        ], $result['hit_dice']);

        $this->assertEquals([
            'available' => 3,
            'max' => 5,
            'spent' => 2,
        ], $result['total']);
    }

    #[Test]
    public function it_returns_hit_dice_for_multiclass_character(): void
    {
        // Arrange: Create a Fighter 5 / Wizard 2 multiclass
        $fighterClass = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'hit_die' => 10,
            'parent_class_id' => null,
        ]);

        $wizardClass = CharacterClass::factory()->create([
            'name' => 'Wizard',
            'hit_die' => 6,
            'parent_class_id' => null,
        ]);

        $character = Character::factory()->create();

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $fighterClass->slug,
            'level' => 5,
            'hit_dice_spent' => 1,
            'is_primary' => true,
            'order' => 1,
        ]);

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $wizardClass->slug,
            'level' => 2,
            'hit_dice_spent' => 0,
            'is_primary' => false,
            'order' => 2,
        ]);

        // Act
        $result = $this->service->getHitDice($character);

        // Assert
        $this->assertEquals([
            'd10' => ['available' => 4, 'max' => 5, 'spent' => 1],
            'd6' => ['available' => 2, 'max' => 2, 'spent' => 0],
        ], $result['hit_dice']);

        $this->assertEquals([
            'available' => 6,
            'max' => 7,
            'spent' => 1,
        ], $result['total']);
    }

    #[Test]
    public function it_spends_hit_dice_of_specified_type(): void
    {
        // Arrange
        $fighterClass = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'hit_die' => 10,
            'parent_class_id' => null,
        ]);

        $character = Character::factory()->create();
        $pivot = CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $fighterClass->slug,
            'level' => 5,
            'hit_dice_spent' => 0,
            'is_primary' => true,
        ]);

        // Act
        $result = $this->service->spend($character, 'd10', 2);

        // Assert
        $pivot->refresh();
        $this->assertEquals(2, $pivot->hit_dice_spent);

        $this->assertEquals([
            'd10' => ['available' => 3, 'max' => 5, 'spent' => 2],
        ], $result['hit_dice']);
    }

    #[Test]
    public function it_throws_exception_when_spending_more_than_available(): void
    {
        // Arrange
        $fighterClass = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'hit_die' => 10,
            'parent_class_id' => null,
        ]);

        $character = Character::factory()->create();
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $fighterClass->slug,
            'level' => 3,
            'hit_dice_spent' => 2,
            'is_primary' => true,
        ]);

        // Assert & Act
        $this->expectException(InsufficientHitDiceException::class);
        $this->expectExceptionMessage('Not enough d10 hit dice available. Have 1, need 3.');

        $this->service->spend($character, 'd10', 3);
    }

    #[Test]
    public function it_throws_exception_when_spending_invalid_die_type(): void
    {
        // Arrange: Fighter has d10, not d6
        $fighterClass = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'hit_die' => 10,
            'parent_class_id' => null,
        ]);

        $character = Character::factory()->create();
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $fighterClass->slug,
            'level' => 5,
            'hit_dice_spent' => 0,
            'is_primary' => true,
        ]);

        // Assert & Act
        $this->expectException(InsufficientHitDiceException::class);
        $this->expectExceptionMessage('Character does not have any d6 hit dice.');

        $this->service->spend($character, 'd6', 1);
    }

    #[Test]
    public function it_recovers_specified_quantity_of_hit_dice(): void
    {
        // Arrange
        $fighterClass = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'hit_die' => 10,
            'parent_class_id' => null,
        ]);

        $character = Character::factory()->create();
        $pivot = CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $fighterClass->slug,
            'level' => 6,
            'hit_dice_spent' => 4,
            'is_primary' => true,
        ]);

        // Act
        $result = $this->service->recover($character, 3);

        // Assert
        $pivot->refresh();
        $this->assertEquals(1, $pivot->hit_dice_spent);

        $this->assertEquals(3, $result['recovered']);
        $this->assertEquals([
            'd10' => ['available' => 5, 'max' => 6, 'spent' => 1],
        ], $result['hit_dice']);
    }

    #[Test]
    public function it_recovers_half_total_when_quantity_is_null(): void
    {
        // Arrange: Level 7 character = 7 max, recover floor(7/2) = 3
        $fighterClass = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'hit_die' => 10,
            'parent_class_id' => null,
        ]);

        $character = Character::factory()->create();
        $pivot = CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $fighterClass->slug,
            'level' => 7,
            'hit_dice_spent' => 7, // All spent
            'is_primary' => true,
        ]);

        // Act
        $result = $this->service->recover($character);

        // Assert
        $pivot->refresh();
        $this->assertEquals(4, $pivot->hit_dice_spent); // 7 - 3 = 4

        $this->assertEquals(3, $result['recovered']);
    }

    #[Test]
    public function it_recovers_minimum_one_when_total_is_one(): void
    {
        // Arrange: Level 1 character = 1 max, recover max(1, floor(1/2)) = 1
        $fighterClass = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'hit_die' => 10,
            'parent_class_id' => null,
        ]);

        $character = Character::factory()->create();
        $pivot = CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $fighterClass->slug,
            'level' => 1,
            'hit_dice_spent' => 1,
            'is_primary' => true,
        ]);

        // Act
        $result = $this->service->recover($character);

        // Assert
        $pivot->refresh();
        $this->assertEquals(0, $pivot->hit_dice_spent);

        $this->assertEquals(1, $result['recovered']);
    }

    #[Test]
    public function it_recovers_larger_dice_first_for_multiclass(): void
    {
        // Arrange: Fighter 3 (d10) / Wizard 3 (d6), all spent, recover 3
        $fighterClass = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'hit_die' => 10,
            'parent_class_id' => null,
        ]);

        $wizardClass = CharacterClass::factory()->create([
            'name' => 'Wizard',
            'hit_die' => 6,
            'parent_class_id' => null,
        ]);

        $character = Character::factory()->create();

        $fighterPivot = CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $fighterClass->slug,
            'level' => 3,
            'hit_dice_spent' => 3,
            'is_primary' => true,
            'order' => 1,
        ]);

        $wizardPivot = CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $wizardClass->slug,
            'level' => 3,
            'hit_dice_spent' => 3,
            'is_primary' => false,
            'order' => 2,
        ]);

        // Act: Recover 3 dice - should prioritize d10
        $result = $this->service->recover($character, 3);

        // Assert
        $fighterPivot->refresh();
        $wizardPivot->refresh();

        // All 3 recovered should be d10s (larger dice first)
        $this->assertEquals(0, $fighterPivot->hit_dice_spent);
        $this->assertEquals(3, $wizardPivot->hit_dice_spent);

        $this->assertEquals(3, $result['recovered']);
    }

    #[Test]
    public function it_does_not_recover_more_than_spent(): void
    {
        // Arrange: 2 spent, try to recover 5
        $fighterClass = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'hit_die' => 10,
            'parent_class_id' => null,
        ]);

        $character = Character::factory()->create();
        $pivot = CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $fighterClass->slug,
            'level' => 6,
            'hit_dice_spent' => 2,
            'is_primary' => true,
        ]);

        // Act: Only 2 are spent, so only 2 can be recovered
        $result = $this->service->recover($character, 5);

        // Assert
        $pivot->refresh();
        $this->assertEquals(0, $pivot->hit_dice_spent);

        $this->assertEquals(2, $result['recovered']); // Only recovered what was spent
    }
}
