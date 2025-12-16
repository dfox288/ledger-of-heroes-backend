<?php

namespace Tests\Feature\Importers;

use App\Enums\ResetTiming;
use App\Models\EntityCounter;
use App\Models\Feat;
use App\Services\Importers\FeatImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('importers')]
class FeatImporterCounterTest extends TestCase
{
    use RefreshDatabase;

    private FeatImporter $importer;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = new FeatImporter;
    }

    #[Test]
    public function it_creates_counter_record_for_lucky_feat(): void
    {
        $featData = [
            'name' => 'Lucky',
            'prerequisites' => null,
            'description' => 'You have inexplicable luck. You have 3 luck points. You regain your expended luck points when you finish a long rest.',
            'sources' => [],
            'modifiers' => [],
            'proficiencies' => [],
            'conditions' => [],
            'spells' => [],
            'languages' => [],
            'movement_modifiers' => [],
            'resistances' => [],
            'resets_on' => ResetTiming::LONG_REST,
            'base_uses' => 3,
            'uses_formula' => null,
        ];

        $feat = $this->importer->import($featData);

        $this->assertInstanceOf(Feat::class, $feat);

        // Verify counter was created using polymorphic columns
        $counter = EntityCounter::where('reference_type', Feat::class)
            ->where('reference_id', $feat->id)
            ->first();

        $this->assertNotNull($counter);
        $this->assertEquals(3, $counter->counter_value);
        $this->assertEquals('L', $counter->reset_timing);
        $this->assertEquals(1, $counter->level);
    }

    #[Test]
    public function it_creates_counter_for_magic_initiate_with_single_use(): void
    {
        $featData = [
            'name' => 'Magic Initiate (Bard)',
            'prerequisites' => null,
            'description' => 'You learn two bard cantrips. Once you cast it, you must finish a long rest before you can cast it again.',
            'sources' => [],
            'modifiers' => [],
            'proficiencies' => [],
            'conditions' => [],
            'spells' => [],
            'languages' => [],
            'movement_modifiers' => [],
            'resistances' => [],
            'resets_on' => ResetTiming::LONG_REST,
            'base_uses' => 1,
            'uses_formula' => null,
        ];

        $feat = $this->importer->import($featData);
        $counter = EntityCounter::where('reference_type', Feat::class)
            ->where('reference_id', $feat->id)
            ->first();

        $this->assertNotNull($counter);
        $this->assertEquals(1, $counter->counter_value);
        $this->assertEquals('L', $counter->reset_timing);
    }

    #[Test]
    public function it_creates_counter_for_martial_adept_with_short_rest_reset(): void
    {
        $featData = [
            'name' => 'Martial Adept',
            'prerequisites' => null,
            'description' => 'You gain one superiority die. You regain your expended dice when you finish a short or long rest.',
            'sources' => [],
            'modifiers' => [],
            'proficiencies' => [],
            'conditions' => [],
            'spells' => [],
            'languages' => [],
            'movement_modifiers' => [],
            'resistances' => [],
            'resets_on' => ResetTiming::SHORT_REST,
            'base_uses' => 1,
            'uses_formula' => null,
        ];

        $feat = $this->importer->import($featData);
        $counter = EntityCounter::where('reference_type', Feat::class)
            ->where('reference_id', $feat->id)
            ->first();

        $this->assertNotNull($counter);
        $this->assertEquals(1, $counter->counter_value);
        $this->assertEquals('S', $counter->reset_timing);
    }

    #[Test]
    public function it_does_not_create_counter_for_feats_without_usage_limits(): void
    {
        $featData = [
            'name' => 'Alert',
            'prerequisites' => null,
            'description' => 'Always on the lookout for danger. You gain a +5 bonus to initiative.',
            'sources' => [],
            'modifiers' => [],
            'proficiencies' => [],
            'conditions' => [],
            'spells' => [],
            'languages' => [],
            'movement_modifiers' => [],
            'resistances' => [],
            'resets_on' => null,
            'base_uses' => null,
            'uses_formula' => null,
        ];

        $feat = $this->importer->import($featData);

        // No counter should be created
        $counter = EntityCounter::where('reference_type', Feat::class)
            ->where('reference_id', $feat->id)
            ->first();
        $this->assertNull($counter);
    }

    #[Test]
    public function it_updates_counter_on_reimport(): void
    {
        $featData = [
            'name' => 'Lucky',
            'prerequisites' => null,
            'description' => 'You have 3 luck points. You regain your expended luck points when you finish a long rest.',
            'sources' => [],
            'modifiers' => [],
            'proficiencies' => [],
            'conditions' => [],
            'spells' => [],
            'languages' => [],
            'movement_modifiers' => [],
            'resistances' => [],
            'resets_on' => ResetTiming::LONG_REST,
            'base_uses' => 3,
            'uses_formula' => null,
        ];

        // Import twice
        $this->importer->import($featData);
        $feat = $this->importer->import($featData);

        // Should only have one counter (updated, not duplicated)
        $counters = EntityCounter::where('reference_type', Feat::class)
            ->where('reference_id', $feat->id)
            ->get();
        $this->assertCount(1, $counters);
        $this->assertEquals(3, $counters->first()->counter_value);
    }
}
