<?php

namespace Tests\Unit\Factories;

use App\Models\CharacterClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterClassFactoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_a_class_with_valid_data()
    {
        $class = CharacterClass::factory()->create();

        $this->assertInstanceOf(CharacterClass::class, $class);
        $this->assertNotNull($class->name);
        $this->assertContains($class->hit_die, [6, 8, 10, 12]);
        $this->assertNull($class->parent_class_id);
    }

    #[Test]
    public function it_creates_spellcaster_with_state()
    {
        $class = CharacterClass::factory()->spellcaster('INT')->create();

        $this->assertNotNull($class->spellcasting_ability_id);
        $this->assertEquals('INT', $class->spellcastingAbility->code);
    }

    #[Test]
    public function it_creates_subclass_with_state()
    {
        $parentClass = CharacterClass::factory()->create(['name' => 'Fighter']);
        $subclass = CharacterClass::factory()->subclass($parentClass)->create();

        $this->assertEquals($parentClass->id, $subclass->parent_class_id);
    }
}
