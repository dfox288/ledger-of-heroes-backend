<?php

namespace Tests\Unit\Factories;

use App\Models\Spell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpellFactoryTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_creates_a_spell_with_valid_data()
    {
        $spell = Spell::factory()->create();

        $this->assertInstanceOf(Spell::class, $spell);
        $this->assertNotNull($spell->name);
        $this->assertNotNull($spell->spell_school_id);
        $this->assertGreaterThanOrEqual(0, $spell->level);
        $this->assertLessThanOrEqual(9, $spell->level);
    }

    /** @test */
    public function it_creates_cantrip_with_state()
    {
        $spell = Spell::factory()->cantrip()->create();

        $this->assertEquals(0, $spell->level);
    }

    /** @test */
    public function it_creates_concentration_spell_with_state()
    {
        $spell = Spell::factory()->concentration()->create();

        $this->assertTrue($spell->needs_concentration);
        $this->assertStringContainsString('Concentration', $spell->duration);
    }

    /** @test */
    public function it_creates_ritual_spell_with_state()
    {
        $spell = Spell::factory()->ritual()->create();

        $this->assertTrue($spell->is_ritual);
    }
}
