<?php

namespace Tests\Feature\Models;

use App\Models\Race;
use App\Models\Size;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RaceModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_race_can_have_parent_race(): void
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

    public function test_base_race_has_null_parent(): void
    {
        $baseRace = Race::factory()->create([
            'name' => 'Dragonborn',
            'speed' => 30,
            'parent_race_id' => null,
        ]);

        $this->assertNull($baseRace->parent);
        $this->assertCount(0, $baseRace->subraces);
    }
}
