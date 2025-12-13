<?php

namespace Tests\Feature\Models;

use App\Models\EntityLanguage;
use App\Models\EntitySense;
use App\Models\Language;
use App\Models\Race;
use App\Models\Sense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for Race model relationships and edge cases.
 *
 * @see https://github.com/dfox288/ledger-of-heroes/issues/581
 */
#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class RaceModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function race_can_have_parent_race(): void
    {
        // Arrange: Create base race and subrace
        $baseRace = Race::factory()->create([
            'name' => 'Dwarf',
            'speed' => 25,
            'parent_race_id' => null,
        ]);

        $subrace = Race::factory()->create([
            'name' => 'Hill',
            'speed' => 25,
            'parent_race_id' => $baseRace->id,
        ]);

        // Act & Assert: Test parent relationship
        $this->assertNotNull($subrace->parent);
        $this->assertEquals('Dwarf', $subrace->parent->name);

        // Assert: Test subraces relationship
        $this->assertCount(1, $baseRace->subraces);
        $this->assertEquals('Hill', $baseRace->subraces->first()->name);
    }

    #[Test]
    public function base_race_has_null_parent(): void
    {
        $baseRace = Race::factory()->create([
            'name' => 'Dragonborn',
            'speed' => 30,
            'parent_race_id' => null,
        ]);

        $this->assertNull($baseRace->parent);
        $this->assertCount(0, $baseRace->subraces);
    }

    #[Test]
    public function race_has_conditions_relationship(): void
    {
        $race = Race::factory()->create();
        $condition = \App\Models\Condition::firstOrCreate(
            ['slug' => 'poisoned'],
            ['name' => 'Poisoned', 'description' => 'Test condition']
        );

        \Illuminate\Support\Facades\DB::table('entity_conditions')->insert([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'condition_id' => $condition->id,
            'effect_type' => 'advantage',
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $race->conditions);
        $this->assertCount(1, $race->fresh()->conditions);
    }

    #[Test]
    public function race_has_spells_relationship(): void
    {
        $race = Race::factory()->create();
        $spell = \App\Models\Spell::factory()->create();

        \App\Models\EntitySpell::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'spell_id' => $spell->id,
            'is_cantrip' => true,
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $race->spells);
        $this->assertCount(1, $race->fresh()->spells);
    }

    // =========================================================================
    // Sense Edge Cases (Issue #581)
    // =========================================================================

    #[Test]
    public function race_with_no_senses_returns_empty_collection(): void
    {
        $race = Race::factory()->create();

        $this->assertCount(0, $race->senses);
        $this->assertTrue($race->senses->isEmpty());
    }

    #[Test]
    public function race_with_darkvision_sense(): void
    {
        $race = Race::factory()->create();
        $darkvision = Sense::firstOrCreate(
            ['slug' => 'core:darkvision'],
            ['name' => 'Darkvision']
        );

        EntitySense::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'sense_id' => $darkvision->id,
            'range_feet' => 60,
        ]);

        $race = $race->fresh(['senses.sense']);

        $this->assertCount(1, $race->senses);
        $this->assertEquals('core:darkvision', $race->senses->first()->sense->slug);
        $this->assertEquals(60, $race->senses->first()->range_feet);
    }

    #[Test]
    public function race_with_multiple_senses(): void
    {
        $race = Race::factory()->create();
        $darkvision = Sense::firstOrCreate(
            ['slug' => 'core:darkvision'],
            ['name' => 'Darkvision']
        );
        $blindsight = Sense::firstOrCreate(
            ['slug' => 'core:blindsight'],
            ['name' => 'Blindsight']
        );

        EntitySense::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'sense_id' => $darkvision->id,
            'range_feet' => 120,
        ]);
        EntitySense::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'sense_id' => $blindsight->id,
            'range_feet' => 30,
        ]);

        $race = $race->fresh(['senses']);

        $this->assertCount(2, $race->senses);
    }

    #[Test]
    public function subrace_can_have_own_senses(): void
    {
        $baseRace = Race::factory()->create(['parent_race_id' => null]);
        $subrace = Race::factory()->create(['parent_race_id' => $baseRace->id]);

        $darkvision = Sense::firstOrCreate(
            ['slug' => 'core:darkvision'],
            ['name' => 'Darkvision']
        );

        // Base race has 60ft darkvision
        EntitySense::create([
            'reference_type' => Race::class,
            'reference_id' => $baseRace->id,
            'sense_id' => $darkvision->id,
            'range_feet' => 60,
        ]);

        // Subrace has 120ft darkvision (Drow-like)
        EntitySense::create([
            'reference_type' => Race::class,
            'reference_id' => $subrace->id,
            'sense_id' => $darkvision->id,
            'range_feet' => 120,
        ]);

        $baseRace = $baseRace->fresh(['senses']);
        $subrace = $subrace->fresh(['senses']);

        $this->assertCount(1, $baseRace->senses);
        $this->assertEquals(60, $baseRace->senses->first()->range_feet);

        $this->assertCount(1, $subrace->senses);
        $this->assertEquals(120, $subrace->senses->first()->range_feet);
    }

    // =========================================================================
    // Language Edge Cases (Issue #581)
    // =========================================================================

    #[Test]
    public function race_with_no_languages_returns_empty_collection(): void
    {
        $race = Race::factory()->create();

        $this->assertCount(0, $race->languages);
        $this->assertTrue($race->languages->isEmpty());
    }

    #[Test]
    public function race_with_single_language(): void
    {
        $race = Race::factory()->create();
        $language = Language::create([
            'slug' => 'test:common-'.uniqid(),
            'name' => 'Test Common '.uniqid(),
        ]);

        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'language_id' => $language->id,
        ]);

        $race = $race->fresh(['languages.language']);

        $this->assertCount(1, $race->languages);
        $this->assertNotNull($race->languages->first()->language->name);
    }

    #[Test]
    public function race_with_multiple_languages(): void
    {
        $race = Race::factory()->create();
        $lang1 = Language::create(['slug' => 'test:lang1-'.uniqid(), 'name' => 'Test Lang1 '.uniqid()]);
        $lang2 = Language::create(['slug' => 'test:lang2-'.uniqid(), 'name' => 'Test Lang2 '.uniqid()]);

        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'language_id' => $lang1->id,
        ]);
        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'language_id' => $lang2->id,
        ]);

        $race = $race->fresh(['languages']);

        $this->assertCount(2, $race->languages);
    }

    // =========================================================================
    // Subrace Edge Cases (Issue #581)
    // =========================================================================

    #[Test]
    public function is_subrace_accessor_returns_true_for_subrace(): void
    {
        $baseRace = Race::factory()->create(['parent_race_id' => null]);
        $subrace = Race::factory()->create(['parent_race_id' => $baseRace->id]);

        $this->assertFalse($baseRace->is_subrace);
        $this->assertTrue($subrace->is_subrace);
    }

    #[Test]
    public function race_with_multiple_subraces(): void
    {
        $elf = Race::factory()->create(['name' => 'Elf', 'parent_race_id' => null]);

        $highElf = Race::factory()->create(['name' => 'High Elf', 'parent_race_id' => $elf->id]);
        $woodElf = Race::factory()->create(['name' => 'Wood Elf', 'parent_race_id' => $elf->id]);
        $drow = Race::factory()->create(['name' => 'Drow', 'parent_race_id' => $elf->id]);

        $elf = $elf->fresh(['subraces']);

        $this->assertCount(3, $elf->subraces);
        $this->assertTrue($elf->subraces->contains('name', 'High Elf'));
        $this->assertTrue($elf->subraces->contains('name', 'Wood Elf'));
        $this->assertTrue($elf->subraces->contains('name', 'Drow'));
    }

    #[Test]
    public function race_with_subrace_required_flag(): void
    {
        $race = Race::factory()->create([
            'subrace_required' => true,
            'parent_race_id' => null,
        ]);

        $this->assertTrue($race->subrace_required);
    }

    #[Test]
    public function race_with_size_choice_flag(): void
    {
        $race = Race::factory()->create([
            'has_size_choice' => true,
        ]);

        $this->assertTrue($race->has_size_choice);
    }

    // =========================================================================
    // Speed Edge Cases (Issue #581)
    // =========================================================================

    #[Test]
    public function race_with_alternate_movement_speeds(): void
    {
        $race = Race::factory()->create([
            'speed' => 30,
            'fly_speed' => 50,
            'swim_speed' => 30,
            'climb_speed' => null,
        ]);

        $this->assertEquals(30, $race->speed);
        $this->assertEquals(50, $race->fly_speed);
        $this->assertEquals(30, $race->swim_speed);
        $this->assertNull($race->climb_speed);
    }

    #[Test]
    public function race_with_no_alternate_movement_speeds(): void
    {
        $race = Race::factory()->create([
            'speed' => 30,
            'fly_speed' => null,
            'swim_speed' => null,
            'climb_speed' => null,
        ]);

        $this->assertEquals(30, $race->speed);
        $this->assertNull($race->fly_speed);
        $this->assertNull($race->swim_speed);
        $this->assertNull($race->climb_speed);
    }

    #[Test]
    public function race_with_slow_speed(): void
    {
        // Dwarves have 25 ft speed
        $race = Race::factory()->create(['speed' => 25]);

        $this->assertEquals(25, $race->speed);
    }
}
