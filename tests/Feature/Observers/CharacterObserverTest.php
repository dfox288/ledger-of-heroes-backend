<?php

declare(strict_types=1);

namespace Tests\Feature\Observers;

use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterObserverTest extends TestCase
{
    use RefreshDatabase;

    private CharacterClass $fighter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fighter = CharacterClass::factory()->create([
            'slug' => 'fighter',
            'name' => 'Fighter',
            'hit_die' => 10,
            'parent_class_id' => null,
        ]);
    }

    #[Test]
    public function it_recalculates_hp_when_con_increases(): void
    {
        $character = Character::factory()->create([
            'constitution' => 14, // +2
            'max_hit_points' => 50,
            'current_hit_points' => 50,
            'hp_calculation_method' => 'calculated',
        ]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $this->fighter->full_slug,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        // CON 14 (+2) -> CON 16 (+3) = +1 per level = +5 HP
        $character->update(['constitution' => 16]);

        $this->assertEquals(55, $character->max_hit_points);
        $this->assertEquals(55, $character->current_hit_points);
    }

    #[Test]
    public function it_recalculates_hp_when_con_decreases(): void
    {
        $character = Character::factory()->create([
            'constitution' => 16, // +3
            'max_hit_points' => 55,
            'current_hit_points' => 55,
            'hp_calculation_method' => 'calculated',
        ]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $this->fighter->full_slug,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        // CON 16 (+3) -> CON 14 (+2) = -1 per level = -5 HP
        $character->update(['constitution' => 14]);

        $this->assertEquals(50, $character->max_hit_points);
        $this->assertEquals(50, $character->current_hit_points);
    }

    #[Test]
    public function it_caps_current_hp_at_new_max_when_con_decreases(): void
    {
        $character = Character::factory()->create([
            'constitution' => 16,
            'max_hit_points' => 55,
            'current_hit_points' => 52, // Slightly damaged
            'hp_calculation_method' => 'calculated',
        ]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $this->fighter->full_slug,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        $character->update(['constitution' => 14]);

        $this->assertEquals(50, $character->max_hit_points);
        $this->assertEquals(50, $character->current_hit_points); // Capped at new max
    }

    #[Test]
    public function it_does_not_recalculate_hp_for_manual_characters(): void
    {
        $character = Character::factory()->create([
            'constitution' => 14,
            'max_hit_points' => 100, // Custom value
            'current_hit_points' => 100,
            'hp_calculation_method' => 'manual',
        ]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $this->fighter->full_slug,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        $character->update(['constitution' => 16]);

        $this->assertEquals(100, $character->max_hit_points); // Unchanged
        $this->assertEquals(100, $character->current_hit_points);
    }

    #[Test]
    public function it_does_not_recalculate_hp_when_modifier_unchanged(): void
    {
        $character = Character::factory()->create([
            'constitution' => 14, // +2
            'max_hit_points' => 50,
            'current_hit_points' => 50,
            'hp_calculation_method' => 'calculated',
        ]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $this->fighter->full_slug,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        // CON 14 -> 15 both have +2 modifier (no change in HP)
        $character->update(['constitution' => 15]);

        $this->assertEquals(50, $character->max_hit_points); // Unchanged
        $this->assertEquals(50, $character->current_hit_points);
    }

    #[Test]
    public function it_handles_level_0_character_gracefully(): void
    {
        // Character with no class (level 0)
        $character = Character::factory()->create([
            'constitution' => 14,
            'max_hit_points' => 10,
            'current_hit_points' => 10,
            'hp_calculation_method' => 'calculated',
        ]);

        // No class pivot = level 0

        // Should not throw or change anything
        $character->update(['constitution' => 16]);

        $this->assertEquals(10, $character->max_hit_points); // Unchanged (0 levels)
        $this->assertEquals(10, $character->current_hit_points);
    }

    #[Test]
    public function it_only_triggers_on_constitution_change(): void
    {
        $character = Character::factory()->create([
            'constitution' => 14,
            'max_hit_points' => 50,
            'current_hit_points' => 50,
            'hp_calculation_method' => 'calculated',
        ]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $this->fighter->full_slug,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        // Change other attributes - HP should not change
        $character->update([
            'strength' => 18,
            'name' => 'Updated Name',
        ]);

        $this->assertEquals(50, $character->max_hit_points);
        $this->assertEquals(50, $character->current_hit_points);
    }
}
