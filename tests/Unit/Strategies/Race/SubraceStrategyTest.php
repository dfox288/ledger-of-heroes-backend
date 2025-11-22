<?php

namespace Tests\Unit\Strategies\Race;

use App\Models\Race;
use App\Models\Size;
use App\Services\Importers\Strategies\Race\SubraceStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SubraceStrategyTest extends TestCase
{
    use RefreshDatabase;

    private SubraceStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new SubraceStrategy;
        config(['scout.driver' => 'null']); // Disable Scout for unit tests
    }

    /**
     * Seed sizes for tests that need database interaction.
     */
    private function seedSizes(): void
    {
        if (! \App\Models\Size::where('code', 'M')->exists()) {
            $this->seed(\Database\Seeders\SizeSeeder::class);
        }
    }

    #[Test]
    public function it_applies_to_subraces(): void
    {
        $data = ['name' => 'High Elf', 'base_race_name' => 'Elf'];

        $this->assertTrue($this->strategy->appliesTo($data));
    }

    #[Test]
    public function it_does_not_apply_to_base_races(): void
    {
        $data = ['name' => 'Elf', 'base_race_name' => null];

        $this->assertFalse($this->strategy->appliesTo($data));
    }

    #[Test]
    public function it_does_not_apply_to_variants(): void
    {
        $data = ['name' => 'High Elf', 'base_race_name' => 'Elf', 'variant_of' => 'Elf'];

        $this->assertFalse($this->strategy->appliesTo($data));
    }

    #[Test]
    public function it_resolves_existing_base_race(): void
    {
        $this->seedSizes();
        $size = Size::where('code', 'M')->first();
        $baseRace = Race::factory()->create([
            'name' => 'Elf',
            'slug' => 'elf',
            'size_id' => $size->id,
        ]);

        $data = [
            'name' => 'High Elf',
            'base_race_name' => 'Elf',
            'size_code' => 'M',
            'speed' => 30,
        ];

        $result = $this->strategy->enhance($data);

        $this->assertEquals($baseRace->id, $result['parent_race_id']);
    }

    #[Test]
    public function it_creates_stub_base_race_if_missing(): void
    {
        $this->seedSizes();
        $this->assertDatabaseMissing('races', ['slug' => 'dwarf']);

        $data = [
            'name' => 'Mountain Dwarf',
            'base_race_name' => 'Dwarf',
            'size_code' => 'M',
            'speed' => 25,
        ];

        $result = $this->strategy->enhance($data);

        $this->assertDatabaseHas('races', [
            'slug' => 'dwarf',
            'name' => 'Dwarf',
        ]);

        $baseRace = Race::where('slug', 'dwarf')->first();
        $this->assertEquals($baseRace->id, $result['parent_race_id']);
    }

    #[Test]
    public function it_generates_compound_slug(): void
    {
        $data = [
            'name' => 'High Elf',
            'base_race_name' => 'Elf',
            'size_code' => 'M',
            'speed' => 30,
        ];

        $result = $this->strategy->enhance($data);

        $this->assertEquals('elf-high-elf', $result['slug']);
    }

    #[Test]
    public function it_tracks_subraces_processed_metric(): void
    {
        $data = [
            'name' => 'High Elf',
            'base_race_name' => 'Elf',
            'size_code' => 'M',
            'speed' => 30,
        ];

        $this->strategy->enhance($data);

        $metrics = $this->strategy->getMetrics();
        $this->assertEquals(1, $metrics['subraces_processed']);
    }

    #[Test]
    public function it_tracks_base_races_created_metric(): void
    {
        $this->seedSizes();

        $data = [
            'name' => 'Mountain Dwarf',
            'base_race_name' => 'Dwarf',
            'size_code' => 'M',
            'speed' => 25,
        ];

        $this->strategy->enhance($data);

        $metrics = $this->strategy->getMetrics();
        $this->assertEquals(1, $metrics['base_races_created']);
    }

    #[Test]
    public function it_tracks_base_races_resolved_metric(): void
    {
        $this->seedSizes();
        $size = Size::where('code', 'M')->first();
        Race::factory()->create(['name' => 'Elf', 'slug' => 'elf', 'size_id' => $size->id]);

        $data = [
            'name' => 'High Elf',
            'base_race_name' => 'Elf',
            'size_code' => 'M',
            'speed' => 30,
        ];

        $this->strategy->enhance($data);

        $metrics = $this->strategy->getMetrics();
        $this->assertEquals(1, $metrics['base_races_resolved']);
    }

    #[Test]
    public function it_warns_if_size_missing_for_stub_creation(): void
    {
        $data = [
            'name' => 'Mountain Dwarf',
            'base_race_name' => 'Dwarf',
            'size_code' => null,
            'speed' => 25,
        ];

        $this->strategy->enhance($data);

        $warnings = $this->strategy->getWarnings();
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('size_code', $warnings[0]);
    }

    #[Test]
    public function it_does_not_apply_when_both_base_race_name_and_variant_of_are_present(): void
    {
        $data = ['name' => 'High Elf', 'base_race_name' => 'Elf', 'variant_of' => 'Elf'];

        $this->assertFalse($this->strategy->appliesTo($data));
    }

    #[Test]
    public function it_generates_hierarchical_slug_using_parent_actual_slug(): void
    {
        $this->seedSizes();
        $size = Size::where('code', 'M')->first();

        // Given: A parent race with a custom slug that doesn't match slugified name
        $parent = Race::factory()->create([
            'name' => 'Dwarf, Mark of Warding',
            'slug' => 'dwarf-mark-warding-custom',  // Custom slug different from Str::slug(name)
            'size_id' => $size->id,
        ]);

        // When: Importing a child subrace (format: "BaseName, SubraceName")
        $data = [
            'name' => 'Dwarf, Mark of Warding (Eberron)',
            'base_race_name' => 'Dwarf, Mark of Warding',
            'size_code' => 'M',
            'speed' => 30,
        ];

        $result = $this->strategy->enhance($data);

        // Then: Child slug should use parent's ACTUAL slug, not re-slugified name
        $this->assertEquals('dwarf-mark-warding-custom-eberron', $result['slug']);
        $this->assertEquals($parent->id, $result['parent_race_id']);
    }
}
