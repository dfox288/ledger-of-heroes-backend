<?php

namespace Tests\Unit\Models;

use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterMulticlassTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_has_many_character_classes(): void
    {
        $character = Character::factory()->create();
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'parent_class_id' => null]);
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'parent_class_id' => null]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $fighter->id,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $wizard->id,
            'level' => 3,
            'is_primary' => false,
            'order' => 2,
        ]);

        $this->assertCount(2, $character->characterClasses);
        $this->assertEquals('Fighter', $character->characterClasses->first()->characterClass->name);
    }

    #[Test]
    public function it_calculates_total_level_from_all_classes(): void
    {
        $character = Character::factory()->create();
        $fighter = CharacterClass::factory()->create(['parent_class_id' => null]);
        $wizard = CharacterClass::factory()->create(['parent_class_id' => null]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $fighter->id,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $wizard->id,
            'level' => 3,
            'is_primary' => false,
            'order' => 2,
        ]);

        $this->assertEquals(8, $character->total_level);
    }

    #[Test]
    public function it_returns_primary_class(): void
    {
        $character = Character::factory()->create();
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'parent_class_id' => null]);
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'parent_class_id' => null]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $fighter->id,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $wizard->id,
            'level' => 3,
            'is_primary' => false,
            'order' => 2,
        ]);

        $this->assertEquals('Fighter', $character->primary_class->name);
    }

    #[Test]
    public function it_detects_multiclass_status(): void
    {
        $character = Character::factory()->create();
        $fighter = CharacterClass::factory()->create(['parent_class_id' => null]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $fighter->id,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        $this->assertFalse($character->fresh()->is_multiclass);

        $wizard = CharacterClass::factory()->create(['parent_class_id' => null]);
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $wizard->id,
            'level' => 1,
            'is_primary' => false,
            'order' => 2,
        ]);

        $this->assertTrue($character->fresh()->is_multiclass);
    }

    #[Test]
    public function character_classes_are_ordered_by_order_column(): void
    {
        $character = Character::factory()->create();
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'parent_class_id' => null]);
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'parent_class_id' => null]);
        $cleric = CharacterClass::factory()->create(['name' => 'Cleric', 'parent_class_id' => null]);

        // Create in non-sequential order
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $cleric->id,
            'level' => 1,
            'is_primary' => false,
            'order' => 3,
        ]);
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $fighter->id,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $wizard->id,
            'level' => 2,
            'is_primary' => false,
            'order' => 2,
        ]);

        $classNames = $character->fresh()->characterClasses->pluck('characterClass.name')->toArray();

        $this->assertEquals(['Fighter', 'Wizard', 'Cleric'], $classNames);
    }
}
