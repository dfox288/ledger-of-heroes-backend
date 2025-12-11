<?php

namespace Tests\Unit\Models;

use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterClassPivotTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_belongs_to_a_character(): void
    {
        $character = Character::factory()->create();
        $class = CharacterClass::factory()->create(['parent_class_id' => null]);

        $pivot = CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $class->slug,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        $this->assertTrue($pivot->character->is($character));
    }

    #[Test]
    public function it_belongs_to_a_class(): void
    {
        $character = Character::factory()->create();
        $class = CharacterClass::factory()->create(['parent_class_id' => null]);

        $pivot = CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $class->slug,
            'level' => 3,
            'is_primary' => true,
            'order' => 1,
        ]);

        $this->assertTrue($pivot->characterClass->is($class));
    }

    #[Test]
    public function it_can_have_a_subclass(): void
    {
        $character = Character::factory()->create();
        $baseClass = CharacterClass::factory()->create(['parent_class_id' => null]);
        $subclass = CharacterClass::factory()->create(['parent_class_id' => $baseClass->id]);

        $pivot = CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $baseClass->slug,
            'subclass_slug' => $subclass->slug,
            'level' => 3,
            'is_primary' => true,
            'order' => 1,
        ]);

        $this->assertTrue($pivot->subclass->is($subclass));
    }

    #[Test]
    public function it_calculates_available_hit_dice(): void
    {
        $character = Character::factory()->create();
        $class = CharacterClass::factory()->create(['parent_class_id' => null]);

        $pivot = CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $class->slug,
            'level' => 5,
            'hit_dice_spent' => 2,
            'is_primary' => true,
            'order' => 1,
        ]);

        $this->assertEquals(5, $pivot->max_hit_dice);
        $this->assertEquals(3, $pivot->available_hit_dice);
    }

    #[Test]
    public function it_enforces_unique_character_class_combination(): void
    {
        $character = Character::factory()->create();
        $class = CharacterClass::factory()->create(['parent_class_id' => null]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $class->slug,
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $class->slug,
            'level' => 1,
            'is_primary' => false,
            'order' => 2,
        ]);
    }
}
