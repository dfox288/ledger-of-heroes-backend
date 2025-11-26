<?php

namespace Tests\Unit\Factories;

use App\Models\SpellEffect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-db')]
class SpellEffectFactoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_a_spell_effect_with_valid_data()
    {
        $effect = SpellEffect::factory()->create();

        $this->assertInstanceOf(SpellEffect::class, $effect);
        $this->assertNotNull($effect->spell_id);
        $this->assertContains($effect->effect_type, ['damage', 'healing', 'other']);
    }

    #[Test]
    public function it_creates_damage_effect_with_state()
    {
        $effect = SpellEffect::factory()->damage('Fire')->create();

        $this->assertEquals('damage', $effect->effect_type);
        $this->assertNotNull($effect->damage_type_id);
        $this->assertNotNull($effect->dice_formula);
        $this->assertEquals('Fire', $effect->damageType->name);
    }

    #[Test]
    public function it_creates_spell_slot_scaling_effect()
    {
        $effect = SpellEffect::factory()->scalingSpellSlot(2, '2d6')->create();

        $this->assertEquals('spell_slot_level', $effect->scaling_type);
        $this->assertEquals(2, $effect->min_spell_slot);
        $this->assertEquals('2d6', $effect->scaling_increment);
    }

    #[Test]
    public function it_creates_character_level_scaling_effect()
    {
        $effect = SpellEffect::factory()->scalingCharacterLevel(5, '1d8')->create();

        $this->assertEquals('character_level', $effect->scaling_type);
        $this->assertEquals(5, $effect->min_character_level);
        $this->assertEquals('1d8', $effect->scaling_increment);
    }
}
