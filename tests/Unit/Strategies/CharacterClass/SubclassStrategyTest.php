<?php

namespace Tests\Unit\Strategies\CharacterClass;

use App\Models\CharacterClass;
use App\Services\Importers\Strategies\CharacterClass\SubclassStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-db')]
class SubclassStrategyTest extends TestCase
{
    use RefreshDatabase;

    private SubclassStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new SubclassStrategy;
        config(['scout.driver' => 'null']); // Disable Scout for unit tests
    }

    #[Test]
    public function it_applies_to_subclasses(): void
    {
        $data = ['name' => 'School of Evocation', 'hit_die' => 0];

        $this->assertTrue($this->strategy->appliesTo($data));
    }

    #[Test]
    public function it_does_not_apply_to_base_classes(): void
    {
        $data = ['name' => 'Wizard', 'hit_die' => 6];

        $this->assertFalse($this->strategy->appliesTo($data));
    }

    #[Test]
    public function it_resolves_parent_from_school_pattern(): void
    {
        $parent = CharacterClass::factory()->create(['name' => 'Wizard', 'slug' => 'wizard']);

        $data = ['name' => 'School of Evocation', 'hit_die' => 0];

        $result = $this->strategy->enhance($data);

        $this->assertEquals($parent->id, $result['parent_class_id']);
    }

    #[Test]
    public function it_resolves_parent_from_oath_pattern(): void
    {
        $parent = CharacterClass::factory()->create(['name' => 'Paladin', 'slug' => 'paladin']);

        $data = ['name' => 'Oath of Vengeance', 'hit_die' => 0];

        $result = $this->strategy->enhance($data);

        $this->assertEquals($parent->id, $result['parent_class_id']);
    }

    #[Test]
    public function it_resolves_parent_from_circle_pattern(): void
    {
        $parent = CharacterClass::factory()->create(['name' => 'Druid', 'slug' => 'druid']);

        $data = ['name' => 'Circle of the Moon', 'hit_die' => 0];

        $result = $this->strategy->enhance($data);

        $this->assertEquals($parent->id, $result['parent_class_id']);
    }

    #[Test]
    public function it_inherits_hit_die_from_parent(): void
    {
        CharacterClass::factory()->create(['name' => 'Wizard', 'slug' => 'wizard', 'hit_die' => 6]);

        $data = ['name' => 'School of Evocation', 'hit_die' => 0];

        $result = $this->strategy->enhance($data);

        $this->assertEquals(6, $result['hit_die']);
    }

    #[Test]
    public function it_generates_subclass_slug(): void
    {
        CharacterClass::factory()->create(['name' => 'Wizard', 'slug' => 'wizard']);

        $data = ['name' => 'School of Evocation', 'hit_die' => 0];

        $result = $this->strategy->enhance($data);

        $this->assertEquals('wizard-school-of-evocation', $result['slug']);
    }

    #[Test]
    public function it_warns_if_parent_not_found(): void
    {
        $data = ['name' => 'School of Evocation', 'hit_die' => 0];

        $this->strategy->enhance($data);

        $warnings = $this->strategy->getWarnings();
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('Parent class', $warnings[0]);
    }

    #[Test]
    public function it_tracks_subclasses_processed_metric(): void
    {
        CharacterClass::factory()->create(['name' => 'Wizard', 'slug' => 'wizard']);

        $data = ['name' => 'School of Evocation', 'hit_die' => 0];

        $this->strategy->enhance($data);

        $metrics = $this->strategy->getMetrics();
        $this->assertEquals(1, $metrics['subclasses_processed']);
    }

    #[Test]
    public function it_tracks_parent_classes_resolved_metric(): void
    {
        CharacterClass::factory()->create(['name' => 'Wizard', 'slug' => 'wizard']);

        $data = ['name' => 'School of Evocation', 'hit_die' => 0];

        $this->strategy->enhance($data);

        $metrics = $this->strategy->getMetrics();
        $this->assertEquals(1, $metrics['parent_classes_resolved']);
    }
}
