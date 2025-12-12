<?php

namespace Tests\Unit\Models;

use App\Models\Spell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for computed Spell accessors.
 *
 * Note: material_cost_gp and material_consumed are now real database columns.
 * Their parsing tests are in SpellXmlParserMaterialTest.
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
            'name' => 'Thunderwave',
            'description' => 'A wave of thunderous force sweeps out from you. Each creature in a 15-foot cube originating from you must make a Constitution saving throw.',
        ]);

        $aoe = $spell->area_of_effect;

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
