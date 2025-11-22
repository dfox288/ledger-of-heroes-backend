<?php

namespace Tests\Unit\Strategies\CharacterClass;

use App\Models\AbilityScore;
use App\Services\Importers\Strategies\CharacterClass\BaseClassStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BaseClassStrategyTest extends TestCase
{
    use RefreshDatabase;

    private BaseClassStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new BaseClassStrategy;

        // Seed necessary lookup data only once
        if (\App\Models\AbilityScore::count() === 0) {
            $this->seed(\Database\Seeders\AbilityScoreSeeder::class);
        }
    }

    #[Test]
    public function it_applies_to_base_classes(): void
    {
        $data = ['name' => 'Wizard', 'hit_die' => 6];

        $this->assertTrue($this->strategy->appliesTo($data));
    }

    #[Test]
    public function it_does_not_apply_to_subclasses(): void
    {
        $data = ['name' => 'School of Evocation', 'hit_die' => 0];

        $this->assertFalse($this->strategy->appliesTo($data));
    }

    #[Test]
    public function it_sets_parent_class_id_to_null(): void
    {
        $data = ['name' => 'Wizard', 'hit_die' => 6];

        $result = $this->strategy->enhance($data);

        $this->assertNull($result['parent_class_id']);
    }

    #[Test]
    public function it_resolves_spellcasting_ability_id(): void
    {
        $data = [
            'name' => 'Wizard',
            'hit_die' => 6,
            'spellcasting_ability' => 'Intelligence',
        ];

        $result = $this->strategy->enhance($data);

        $intelligence = AbilityScore::where('name', 'Intelligence')->first();
        $this->assertEquals($intelligence->id, $result['spellcasting_ability_id']);
    }

    #[Test]
    public function it_tracks_spellcasters_metric(): void
    {
        $data = [
            'name' => 'Wizard',
            'hit_die' => 6,
            'spellcasting_ability' => 'Intelligence',
        ];

        $this->strategy->enhance($data);

        $metrics = $this->strategy->getMetrics();
        $this->assertEquals(1, $metrics['spellcasters_detected']);
    }

    #[Test]
    public function it_tracks_martial_classes_metric(): void
    {
        $data = ['name' => 'Fighter', 'hit_die' => 10];

        $this->strategy->enhance($data);

        $metrics = $this->strategy->getMetrics();
        $this->assertEquals(1, $metrics['martial_classes']);
    }

    #[Test]
    public function it_warns_if_hit_die_missing(): void
    {
        $data = ['name' => 'Wizard', 'hit_die' => null];

        $this->strategy->enhance($data);

        $warnings = $this->strategy->getWarnings();
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('hit_die', $warnings[0]);
    }

    #[Test]
    public function it_tracks_base_classes_processed_metric(): void
    {
        $data = ['name' => 'Wizard', 'hit_die' => 6];

        $this->strategy->enhance($data);

        $metrics = $this->strategy->getMetrics();
        $this->assertEquals(1, $metrics['base_classes_processed']);
    }
}
