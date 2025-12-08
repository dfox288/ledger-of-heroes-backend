<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterHpTrackingTest extends TestCase
{
    use RefreshDatabase;

    private CharacterClass $fighter;

    private CharacterClass $wizard;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fighter = CharacterClass::factory()->create([
            'slug' => 'fighter',
            'name' => 'Fighter',
            'hit_die' => 10,
            'parent_class_id' => null,
        ]);

        $this->wizard = CharacterClass::factory()->create([
            'slug' => 'wizard',
            'name' => 'Wizard',
            'hit_die' => 6,
            'parent_class_id' => null,
        ]);
    }

    #[Test]
    public function it_casts_hp_levels_resolved_as_array(): void
    {
        $character = Character::factory()->create([
            'hp_levels_resolved' => [1, 2],
        ]);

        $this->assertEquals([1, 2], $character->hp_levels_resolved);
        $this->assertIsArray($character->hp_levels_resolved);
    }

    #[Test]
    public function it_returns_false_for_unresolved_level_when_hp_levels_resolved_is_null(): void
    {
        $character = Character::factory()->create([
            'hp_levels_resolved' => null,
        ]);

        $this->assertFalse($character->hasResolvedHpForLevel(1));
    }

    #[Test]
    public function it_returns_empty_array_for_pending_levels_when_hp_levels_resolved_is_null(): void
    {
        $character = Character::factory()->create([
            'hp_levels_resolved' => null,
        ]);

        $this->assertEquals([], $character->getPendingHpLevels());
    }

    #[Test]
    public function it_checks_if_hp_is_resolved_for_a_level(): void
    {
        $character = Character::factory()->create([
            'hp_levels_resolved' => [1, 2],
        ]);

        $this->assertTrue($character->hasResolvedHpForLevel(1));
        $this->assertTrue($character->hasResolvedHpForLevel(2));
        $this->assertFalse($character->hasResolvedHpForLevel(3));
    }

    #[Test]
    public function it_marks_hp_as_resolved_for_a_level(): void
    {
        $character = Character::factory()->create([
            'hp_levels_resolved' => [1],
        ]);

        $character->markHpResolvedForLevel(2);

        $this->assertEquals([1, 2], $character->fresh()->hp_levels_resolved);
    }

    #[Test]
    public function it_does_not_duplicate_resolved_levels(): void
    {
        $character = Character::factory()->create([
            'hp_levels_resolved' => [1, 2],
        ]);

        $character->markHpResolvedForLevel(2);

        $this->assertEquals([1, 2], $character->fresh()->hp_levels_resolved);
    }

    #[Test]
    public function it_keeps_resolved_levels_sorted(): void
    {
        $character = Character::factory()->create([
            'hp_levels_resolved' => [3],
        ]);

        $character->markHpResolvedForLevel(1);

        $this->assertEquals([1, 3], $character->fresh()->hp_levels_resolved);
    }

    #[Test]
    public function it_gets_pending_hp_levels_for_single_class_character(): void
    {
        $character = Character::factory()->create([
            'hp_levels_resolved' => [1],
        ]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $this->fighter->full_slug,
            'level' => 3,
            'is_primary' => true,
            'order' => 1,
        ]);

        $this->assertEquals([2, 3], $character->getPendingHpLevels());
    }

    #[Test]
    public function it_returns_empty_array_when_all_levels_resolved(): void
    {
        $character = Character::factory()->create([
            'hp_levels_resolved' => [1, 2],
        ]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $this->fighter->full_slug,
            'level' => 2,
            'is_primary' => true,
            'order' => 1,
        ]);

        $this->assertEquals([], $character->getPendingHpLevels());
    }

    #[Test]
    public function it_returns_all_levels_as_pending_when_none_resolved(): void
    {
        $character = Character::factory()->create([
            'hp_levels_resolved' => [],
        ]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $this->fighter->full_slug,
            'level' => 3,
            'is_primary' => true,
            'order' => 1,
        ]);

        $this->assertEquals([1, 2, 3], $character->getPendingHpLevels());
    }

    #[Test]
    public function it_checks_calculated_vs_manual_hp_mode(): void
    {
        $calculated = Character::factory()->create([
            'hp_calculation_method' => 'calculated',
        ]);
        $manual = Character::factory()->create([
            'hp_calculation_method' => 'manual',
        ]);

        $this->assertTrue($calculated->usesCalculatedHp());
        $this->assertFalse($manual->usesCalculatedHp());
    }

    #[Test]
    public function it_defaults_to_calculated_hp_mode(): void
    {
        $character = Character::factory()->create();

        $this->assertTrue($character->usesCalculatedHp());
    }

    #[Test]
    public function it_handles_multiclass_character_pending_levels(): void
    {
        $character = Character::factory()->create([
            'hp_levels_resolved' => [1, 2],
        ]);

        // Fighter 2 / Wizard 1 = total level 3
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $this->fighter->full_slug,
            'level' => 2,
            'is_primary' => true,
            'order' => 1,
        ]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $this->wizard->full_slug,
            'level' => 1,
            'is_primary' => false,
            'order' => 2,
        ]);

        $this->assertEquals(3, $character->total_level);
        $this->assertEquals([3], $character->getPendingHpLevels());
    }
}
