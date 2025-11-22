<?php

namespace Tests\Unit\Strategies\Race;

use App\Services\Importers\Strategies\Race\BaseRaceStrategy;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BaseRaceStrategyTest extends TestCase
{
    private BaseRaceStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new BaseRaceStrategy;
    }

    #[Test]
    public function it_applies_to_base_races(): void
    {
        $data = ['name' => 'Elf', 'base_race_name' => null];

        $this->assertTrue($this->strategy->appliesTo($data));
    }

    #[Test]
    public function it_does_not_apply_to_subraces(): void
    {
        $data = ['name' => 'High Elf', 'base_race_name' => 'Elf'];

        $this->assertFalse($this->strategy->appliesTo($data));
    }

    #[Test]
    public function it_does_not_apply_to_variants(): void
    {
        $data = ['name' => 'Dragonborn (Gold)', 'variant_of' => 'Dragonborn'];

        $this->assertFalse($this->strategy->appliesTo($data));
    }

    #[Test]
    public function it_sets_parent_race_id_to_null(): void
    {
        $data = ['name' => 'Elf', 'size_code' => 'M'];

        $result = $this->strategy->enhance($data);

        $this->assertNull($result['parent_race_id']);
    }

    #[Test]
    public function it_tracks_base_races_processed_metric(): void
    {
        $data = ['name' => 'Elf'];

        $this->strategy->enhance($data);

        $metrics = $this->strategy->getMetrics();
        $this->assertEquals(1, $metrics['base_races_processed']);
    }

    #[Test]
    public function it_warns_if_size_missing(): void
    {
        $data = ['name' => 'Elf', 'size_code' => null, 'speed' => 30];

        $this->strategy->enhance($data);

        $warnings = $this->strategy->getWarnings();
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('size_code', $warnings[0]);
    }

    #[Test]
    public function it_warns_if_speed_missing(): void
    {
        $data = ['name' => 'Elf', 'size_code' => 'M', 'speed' => null];

        $this->strategy->enhance($data);

        $warnings = $this->strategy->getWarnings();
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('speed', $warnings[0]);
    }

    #[Test]
    public function it_does_not_modify_other_data(): void
    {
        $data = [
            'name' => 'Elf',
            'size_code' => 'M',
            'speed' => 30,
            'description' => 'Test description',
        ];

        $result = $this->strategy->enhance($data);

        $this->assertEquals('Elf', $result['name']);
        $this->assertEquals('M', $result['size_code']);
        $this->assertEquals(30, $result['speed']);
        $this->assertEquals('Test description', $result['description']);
    }
}
