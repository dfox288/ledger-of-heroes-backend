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
        $size = Size::where('code', 'M')->first();
        $source = Source::where('code', 'PHB')->first();

        $baseRace = Race::create([
            'name' => 'Dwarf',
            'size_id' => $size->id,
            'speed' => 25,
            'description' => 'Base dwarf description',
            'source_id' => $source->id,
            'source_pages' => '20',
            'parent_race_id' => null,
        ]);

        $subrace = Race::create([
            'name' => 'Hill',
            'size_id' => $size->id,
            'speed' => 25,
            'description' => 'Hill dwarf description',
            'source_id' => $source->id,
            'source_pages' => '20',
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
        $size = Size::where('code', 'M')->first();
        $source = Source::where('code', 'PHB')->first();

        $baseRace = Race::create([
            'name' => 'Dragonborn',
            'size_id' => $size->id,
            'speed' => 30,
            'description' => 'Dragonborn description',
            'source_id' => $source->id,
            'source_pages' => '32',
            'parent_race_id' => null,
        ]);

        $this->assertNull($baseRace->parent);
        $this->assertCount(0, $baseRace->subraces);
    }
}
