<?php

namespace Tests\Unit\Strategies\Race;

use App\Models\Race;
use App\Models\Size;
use App\Services\Importers\Strategies\Race\RacialVariantStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-db')]
class RacialVariantStrategyTest extends TestCase
{
    use RefreshDatabase;

    private RacialVariantStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new RacialVariantStrategy;
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
    public function it_applies_to_variants(): void
    {
        $data = ['name' => 'Dragonborn (Gold)', 'variant_of' => 'Dragonborn'];

        $this->assertTrue($this->strategy->appliesTo($data));
    }

    #[Test]
    public function it_does_not_apply_to_base_races(): void
    {
        $data = ['name' => 'Dragonborn', 'variant_of' => null];

        $this->assertFalse($this->strategy->appliesTo($data));
    }

    #[Test]
    public function it_parses_variant_type_from_name(): void
    {
        $data = ['name' => 'Dragonborn (Gold)', 'variant_of' => 'Dragonborn'];

        $result = $this->strategy->enhance($data);

        $this->assertEquals('Gold', $result['variant_type']);
    }

    #[Test]
    public function it_generates_variant_slug(): void
    {
        $data = ['name' => 'Dragonborn (Gold)', 'variant_of' => 'Dragonborn'];

        $result = $this->strategy->enhance($data);

        $this->assertEquals('dragonborn-gold', $result['slug']);
    }

    #[Test]
    public function it_resolves_parent_race(): void
    {
        $this->seedSizes();
        $size = Size::where('code', 'M')->first();
        $parentRace = Race::factory()->create([
            'name' => 'Dragonborn',
            'slug' => 'dragonborn',
            'size_id' => $size->id,
        ]);

        $data = ['name' => 'Dragonborn (Gold)', 'variant_of' => 'Dragonborn'];

        $result = $this->strategy->enhance($data);

        $this->assertEquals($parentRace->id, $result['parent_race_id']);
    }

    #[Test]
    public function it_tracks_variants_processed_metric(): void
    {
        $data = ['name' => 'Dragonborn (Gold)', 'variant_of' => 'Dragonborn'];

        $this->strategy->enhance($data);

        $metrics = $this->strategy->getMetrics();
        $this->assertEquals(1, $metrics['variants_processed']);
    }

    #[Test]
    public function it_warns_if_parent_race_missing(): void
    {
        $data = ['name' => 'Dragonborn (Gold)', 'variant_of' => 'Dragonborn'];

        $this->strategy->enhance($data);

        $warnings = $this->strategy->getWarnings();
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('Parent race', $warnings[0]);
    }

    #[Test]
    public function it_handles_variant_without_parentheses(): void
    {
        $data = ['name' => 'Variant Dragonborn', 'variant_of' => 'Dragonborn'];

        $result = $this->strategy->enhance($data);

        $this->assertArrayNotHasKey('variant_type', $result);
        $this->assertEquals('variant-dragonborn', $result['slug']);
    }

    #[Test]
    public function it_generates_slug_using_parent_actual_slug(): void
    {
        $this->seedSizes();
        $size = Size::where('code', 'M')->first();

        // Given: A parent race with a custom slug that doesn't match slugified name
        $parent = Race::factory()->create([
            'name' => 'Elf, Eladrin',
            'slug' => 'elf-eladrin-custom',  // Custom slug different from Str::slug(name)
            'size_id' => $size->id,
        ]);

        // When: Importing a variant (format: "ParentName (VariantType)")
        $data = [
            'name' => 'Elf, Eladrin (DMG)',
            'variant_of' => 'Elf, Eladrin',
        ];

        $result = $this->strategy->enhance($data);

        // Then: Child slug should use parent's ACTUAL slug, not re-slugified name
        $this->assertEquals('elf-eladrin-custom-dmg', $result['slug']);
        $this->assertEquals($parent->id, $result['parent_race_id']);
    }
}
