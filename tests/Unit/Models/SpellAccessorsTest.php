<?php

namespace Tests\Unit\Models;

use App\Models\Spell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for computed Spell accessors.
 *
 * Note: These accessors use regex parsing which handles ~90% of cases.
 * Edge cases with unusual formatting may not be parsed correctly.
 * See GitHub issues #27 and #28 for known limitations.
 */
#[\PHPUnit\Framework\Attributes\Group('unit-db')]
class SpellAccessorsTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // material_cost_gp accessor tests
    // =========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_parses_material_cost_with_worth_at_least_pattern(): void
    {
        $spell = Spell::factory()->create([
            'name' => 'Arcane Lock',
            'material_components' => 'gold dust worth at least 25 gp, which the spell consumes',
        ]);

        $this->assertEquals(25, $spell->material_cost_gp);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_parses_material_cost_with_worth_pattern(): void
    {
        $spell = Spell::factory()->create([
            'name' => 'Continual Flame',
            'material_components' => 'ruby dust worth 50 gp, which the spell consumes',
        ]);

        $this->assertEquals(50, $spell->material_cost_gp);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_parses_material_cost_with_gp_worth_pattern(): void
    {
        $spell = Spell::factory()->create([
            'name' => 'Find Familiar',
            'material_components' => '10 gp worth of charcoal, incense, and herbs',
        ]);

        $this->assertEquals(10, $spell->material_cost_gp);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_parses_material_cost_with_comma_thousands(): void
    {
        $spell = Spell::factory()->create([
            'name' => 'Awaken',
            'material_components' => 'an agate worth at least 1,000 gp, which the spell consumes',
        ]);

        $this->assertEquals(1000, $spell->material_cost_gp);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_null_for_materials_without_cost(): void
    {
        $spell = Spell::factory()->create([
            'name' => 'Fireball',
            'material_components' => 'a tiny ball of bat guano and sulfur',
        ]);

        $this->assertNull($spell->material_cost_gp);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_null_for_null_materials(): void
    {
        $spell = Spell::factory()->create([
            'name' => 'Magic Missile',
            'material_components' => null,
        ]);

        $this->assertNull($spell->material_cost_gp);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_null_for_empty_materials(): void
    {
        $spell = Spell::factory()->create([
            'name' => 'Fire Bolt',
            'material_components' => '',
        ]);

        $this->assertNull($spell->material_cost_gp);
    }

    // =========================================================================
    // material_consumed accessor tests
    // =========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_consumed_materials(): void
    {
        $spell = Spell::factory()->create([
            'name' => 'Arcane Lock',
            'material_components' => 'gold dust worth at least 25 gp, which the spell consumes',
        ]);

        $this->assertTrue($spell->material_consumed);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_consumed_with_is_consumed_pattern(): void
    {
        $spell = Spell::factory()->create([
            'name' => 'Test Spell',
            'material_components' => 'a gem worth 100 gp that is consumed by the spell',
        ]);

        $this->assertTrue($spell->material_consumed);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_not_consumed_materials(): void
    {
        $spell = Spell::factory()->create([
            'name' => 'Chromatic Orb',
            'material_components' => 'a diamond worth at least 50 gp',
        ]);

        // No "consumes" mentioned = not consumed
        $this->assertFalse($spell->material_consumed);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_false_consumed_for_null_materials(): void
    {
        $spell = Spell::factory()->create([
            'name' => 'Magic Missile',
            'material_components' => null,
        ]);

        $this->assertFalse($spell->material_consumed);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_false_consumed_for_empty_materials(): void
    {
        $spell = Spell::factory()->create([
            'name' => 'Fire Bolt',
            'material_components' => '',
        ]);

        $this->assertFalse($spell->material_consumed);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_false_consumed_for_materials_without_cost(): void
    {
        // Materials without a gold cost are typically not consumed
        $spell = Spell::factory()->create([
            'name' => 'Fireball',
            'material_components' => 'a tiny ball of bat guano and sulfur',
        ]);

        $this->assertFalse($spell->material_consumed);
    }

    // =========================================================================
    // area_of_effect accessor tests
    // =========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_parses_cone_area_of_effect(): void
    {
        $spell = Spell::factory()->create([
            'name' => 'Burning Hands',
            'description' => 'Each creature in a 15-foot cone must make a Dexterity saving throw.',
        ]);

        $aoe = $spell->area_of_effect;

        $this->assertNotNull($aoe);
        $this->assertEquals('cone', $aoe['type']);
        $this->assertEquals(15, $aoe['size']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_parses_sphere_area_of_effect(): void
    {
        $spell = Spell::factory()->create([
            'name' => 'Fireball',
            'description' => 'Each creature in a 20-foot-radius sphere centered on that point must make a Dexterity saving throw.',
        ]);

        $aoe = $spell->area_of_effect;

        $this->assertNotNull($aoe);
        $this->assertEquals('sphere', $aoe['type']);
        $this->assertEquals(20, $aoe['size']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_parses_cube_area_of_effect(): void
    {
        $spell = Spell::factory()->create([
            'name' => 'Fog Cloud',
            'description' => 'You create a 20-foot-radius sphere of fog centered on a point within range.',
        ]);

        // Actually let's test a real cube
        $spell2 = Spell::factory()->create([
            'name' => 'Thunderwave',
            'description' => 'A wave of thunderous force sweeps out from you. Each creature in a 15-foot cube originating from you must make a Constitution saving throw.',
        ]);

        $aoe = $spell2->area_of_effect;

        $this->assertNotNull($aoe);
        $this->assertEquals('cube', $aoe['type']);
        $this->assertEquals(15, $aoe['size']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_parses_line_area_of_effect(): void
    {
        $spell = Spell::factory()->create([
            'name' => 'Lightning Bolt',
            'description' => 'A stroke of lightning forming a line 100 feet long and 5 feet wide blasts out from you.',
        ]);

        $aoe = $spell->area_of_effect;

        $this->assertNotNull($aoe);
        $this->assertEquals('line', $aoe['type']);
        $this->assertEquals(100, $aoe['size']);
        $this->assertEquals(5, $aoe['width']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_parses_cylinder_area_of_effect(): void
    {
        $spell = Spell::factory()->create([
            'name' => 'Flame Strike',
            'description' => 'A vertical column of divine fire roars down from the heavens in a location you specify. Each creature in a 10-foot-radius, 40-foot-high cylinder centered on a point within range must make a Dexterity saving throw.',
        ]);

        $aoe = $spell->area_of_effect;

        $this->assertNotNull($aoe);
        $this->assertEquals('cylinder', $aoe['type']);
        $this->assertEquals(10, $aoe['size']);
        $this->assertEquals(40, $aoe['height']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_null_for_spells_without_area(): void
    {
        $spell = Spell::factory()->create([
            'name' => 'Magic Missile',
            'description' => 'You create three glowing darts of magical force. Each dart hits a creature of your choice that you can see within range.',
        ]);

        $this->assertNull($spell->area_of_effect);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_null_for_empty_description(): void
    {
        $spell = Spell::factory()->create([
            'name' => 'Test Spell',
            'description' => '',
        ]);

        $this->assertNull($spell->area_of_effect);
    }
}
