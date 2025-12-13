<?php

namespace Tests\Feature\Models;

use App\Models\CharacterClass;
use App\Models\Spell;
use App\Models\SpellSchool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Edge case tests for Spell model.
 *
 * Tests relationships, accessors, and boundary conditions.
 *
 * @see https://github.com/dfox288/ledger-of-heroes/issues/581
 */
#[Group('feature-db')]
class SpellModelTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // Level Edge Cases
    // =========================================================================

    #[Test]
    public function cantrip_has_level_zero(): void
    {
        $spell = Spell::factory()->create(['level' => 0]);

        $this->assertEquals(0, $spell->level);
    }

    #[Test]
    public function spell_level_range_1_to_9(): void
    {
        $spell1 = Spell::factory()->create(['level' => 1]);
        $spell5 = Spell::factory()->create(['level' => 5]);
        $spell9 = Spell::factory()->create(['level' => 9]);

        $this->assertEquals(1, $spell1->level);
        $this->assertEquals(5, $spell5->level);
        $this->assertEquals(9, $spell9->level);
    }

    // =========================================================================
    // Casting Time Type Accessor
    // =========================================================================

    #[Test]
    public function casting_time_type_returns_action(): void
    {
        $spell = Spell::factory()->create(['casting_time' => '1 action']);

        $this->assertEquals('action', $spell->casting_time_type);
    }

    #[Test]
    public function casting_time_type_returns_bonus_action(): void
    {
        $spell = Spell::factory()->create(['casting_time' => '1 bonus action']);

        $this->assertEquals('bonus_action', $spell->casting_time_type);
    }

    #[Test]
    public function casting_time_type_returns_reaction(): void
    {
        $spell = Spell::factory()->create(['casting_time' => '1 reaction, which you take when you see a creature within 60 feet']);

        $this->assertEquals('reaction', $spell->casting_time_type);
    }

    #[Test]
    public function casting_time_type_returns_minute(): void
    {
        $spell = Spell::factory()->create(['casting_time' => '1 minute']);

        $this->assertEquals('minute', $spell->casting_time_type);
    }

    #[Test]
    public function casting_time_type_returns_hour(): void
    {
        $spell = Spell::factory()->create(['casting_time' => '1 hour']);

        $this->assertEquals('hour', $spell->casting_time_type);
    }

    #[Test]
    public function casting_time_type_returns_unknown_for_empty(): void
    {
        $spell = Spell::factory()->create(['casting_time' => '']);

        $this->assertEquals('unknown', $spell->casting_time_type);
    }

    // =========================================================================
    // Class Relationship Edge Cases
    // =========================================================================

    #[Test]
    public function spell_with_no_classes_returns_empty_collection(): void
    {
        $spell = Spell::factory()->create();

        $this->assertCount(0, $spell->classes);
        $this->assertTrue($spell->classes->isEmpty());
    }

    #[Test]
    public function spell_with_single_class(): void
    {
        $spell = Spell::factory()->create();
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'parent_class_id' => null]);

        $spell->classes()->attach($wizard->id);

        $spell = $spell->fresh(['classes']);

        $this->assertCount(1, $spell->classes);
        $this->assertEquals('Wizard', $spell->classes->first()->name);
    }

    #[Test]
    public function spell_with_multiple_classes(): void
    {
        $spell = Spell::factory()->create();
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'parent_class_id' => null]);
        $sorcerer = CharacterClass::factory()->create(['name' => 'Sorcerer', 'parent_class_id' => null]);
        $warlock = CharacterClass::factory()->create(['name' => 'Warlock', 'parent_class_id' => null]);

        $spell->classes()->attach([$wizard->id, $sorcerer->id, $warlock->id]);

        $spell = $spell->fresh(['classes']);

        $this->assertCount(3, $spell->classes);
    }

    // =========================================================================
    // Spell School Relationship
    // =========================================================================

    #[Test]
    public function spell_belongs_to_spell_school(): void
    {
        $school = SpellSchool::firstOrCreate(
            ['code' => 'EV'],
            ['name' => 'Evocation']
        );
        $spell = Spell::factory()->create(['spell_school_id' => $school->id]);

        $this->assertNotNull($spell->spellSchool);
        $this->assertEquals('Evocation', $spell->spellSchool->name);
    }

    // =========================================================================
    // Concentration and Ritual Flags
    // =========================================================================

    #[Test]
    public function spell_with_concentration(): void
    {
        $spell = Spell::factory()->create(['needs_concentration' => true]);

        $this->assertTrue($spell->needs_concentration);
    }

    #[Test]
    public function spell_without_concentration(): void
    {
        $spell = Spell::factory()->create(['needs_concentration' => false]);

        $this->assertFalse($spell->needs_concentration);
    }

    #[Test]
    public function spell_with_ritual(): void
    {
        $spell = Spell::factory()->create(['is_ritual' => true]);

        $this->assertTrue($spell->is_ritual);
    }

    #[Test]
    public function spell_without_ritual(): void
    {
        $spell = Spell::factory()->create(['is_ritual' => false]);

        $this->assertFalse($spell->is_ritual);
    }

    // =========================================================================
    // Component Edge Cases
    // =========================================================================

    #[Test]
    public function spell_with_verbal_only_components(): void
    {
        $spell = Spell::factory()->create(['components' => 'V']);

        $this->assertEquals('V', $spell->components);
    }

    #[Test]
    public function spell_with_all_components(): void
    {
        $spell = Spell::factory()->create([
            'components' => 'V, S, M',
            'material_components' => 'a pinch of sulfur',
        ]);

        $this->assertEquals('V, S, M', $spell->components);
        $this->assertEquals('a pinch of sulfur', $spell->material_components);
    }

    #[Test]
    public function spell_with_costly_material_components(): void
    {
        $spell = Spell::factory()->create([
            'components' => 'V, S, M',
            'material_components' => 'a diamond worth at least 500 gp',
            'material_cost_gp' => 500,
            'material_consumed' => true,
        ]);

        $this->assertEquals(500, $spell->material_cost_gp);
        $this->assertTrue($spell->material_consumed);
    }

    #[Test]
    public function spell_without_material_components(): void
    {
        $spell = Spell::factory()->create([
            'components' => 'V, S',
            'material_components' => null,
            'material_cost_gp' => null,
        ]);

        $this->assertNull($spell->material_components);
        $this->assertNull($spell->material_cost_gp);
    }

    // =========================================================================
    // Range Edge Cases
    // =========================================================================

    #[Test]
    public function spell_with_self_range(): void
    {
        $spell = Spell::factory()->create(['range' => 'Self']);

        $this->assertEquals('Self', $spell->range);
    }

    #[Test]
    public function spell_with_touch_range(): void
    {
        $spell = Spell::factory()->create(['range' => 'Touch']);

        $this->assertEquals('Touch', $spell->range);
    }

    #[Test]
    public function spell_with_feet_range(): void
    {
        $spell = Spell::factory()->create(['range' => '120 feet']);

        $this->assertEquals('120 feet', $spell->range);
    }

    #[Test]
    public function spell_with_unlimited_range(): void
    {
        $spell = Spell::factory()->create(['range' => 'Unlimited']);

        $this->assertEquals('Unlimited', $spell->range);
    }

    // =========================================================================
    // Duration Edge Cases
    // =========================================================================

    #[Test]
    public function spell_with_instantaneous_duration(): void
    {
        $spell = Spell::factory()->create(['duration' => 'Instantaneous']);

        $this->assertEquals('Instantaneous', $spell->duration);
    }

    #[Test]
    public function spell_with_concentration_duration(): void
    {
        $spell = Spell::factory()->create([
            'duration' => 'Concentration, up to 1 minute',
            'needs_concentration' => true,
        ]);

        $this->assertEquals('Concentration, up to 1 minute', $spell->duration);
        $this->assertTrue($spell->needs_concentration);
    }

    #[Test]
    public function spell_with_permanent_duration(): void
    {
        $spell = Spell::factory()->create(['duration' => 'Until dispelled']);

        $this->assertEquals('Until dispelled', $spell->duration);
    }
}
