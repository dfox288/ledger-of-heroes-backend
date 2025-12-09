<?php

namespace Tests\Feature\Models;

use App\Models\Race;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

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
}
