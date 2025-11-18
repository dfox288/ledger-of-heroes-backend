<?php

namespace Tests\Unit\Factories;

use App\Models\CharacterClass;
use App\Models\CharacterTrait;
use App\Models\Modifier;
use App\Models\Proficiency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PolymorphicFactoriesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_character_trait()
    {
        $trait = CharacterTrait::factory()->create();

        $this->assertInstanceOf(CharacterTrait::class, $trait);
        $this->assertNotNull($trait->reference_type);
        $this->assertNotNull($trait->reference_id);
    }

    #[Test]
    public function it_creates_trait_for_specific_entity()
    {
        $class = CharacterClass::factory()->create();
        $trait = CharacterTrait::factory()
            ->forEntity(CharacterClass::class, $class->id)
            ->create();

        $this->assertEquals(CharacterClass::class, $trait->reference_type);
        $this->assertEquals($class->id, $trait->reference_id);
    }

    #[Test]
    public function it_creates_proficiency()
    {
        $proficiency = Proficiency::factory()->create();

        $this->assertInstanceOf(Proficiency::class, $proficiency);
        $this->assertNotNull($proficiency->reference_type);
    }

    #[Test]
    public function it_creates_skill_proficiency()
    {
        $proficiency = Proficiency::factory()->skill('Athletics')->create();

        $this->assertEquals('skill', $proficiency->proficiency_type);
        $this->assertEquals('Athletics', $proficiency->skill->name);
    }

    #[Test]
    public function it_creates_modifier()
    {
        $modifier = Modifier::factory()->create();

        $this->assertInstanceOf(Modifier::class, $modifier);
        $this->assertNotNull($modifier->reference_type);
    }

    #[Test]
    public function it_creates_ability_score_modifier()
    {
        $modifier = Modifier::factory()->abilityScore('STR', '+2')->create();

        $this->assertEquals('ability_score', $modifier->modifier_category);
        $this->assertEquals('STR', $modifier->abilityScore->code);
        $this->assertEquals('+2', $modifier->value);
    }
}
