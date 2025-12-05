<?php

namespace Tests\Unit\Services\Importers\Concerns;

use App\Models\EntitySense;
use App\Models\Monster;
use App\Models\Race;
use App\Models\Sense;
use App\Services\Importers\Concerns\ImportsSenses;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-db')]
class ImportsSensesTest extends TestCase
{
    use ImportsSenses;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure senses are seeded
        Sense::firstOrCreate(['slug' => 'darkvision'], ['name' => 'Darkvision']);
        Sense::firstOrCreate(['slug' => 'blindsight'], ['name' => 'Blindsight']);
        Sense::firstOrCreate(['slug' => 'tremorsense'], ['name' => 'Tremorsense']);
        Sense::firstOrCreate(['slug' => 'truesight'], ['name' => 'Truesight']);
    }

    #[Test]
    public function it_imports_single_sense_for_entity()
    {
        $race = Race::factory()->create();

        $sensesData = [
            [
                'type' => 'darkvision',
                'range' => 60,
                'is_limited' => false,
                'notes' => null,
            ],
        ];

        $this->importEntitySenses($race, $sensesData);

        $this->assertCount(1, $race->senses);
        $sense = $race->senses->first();
        $this->assertEquals('darkvision', $sense->sense->slug);
        $this->assertEquals(60, $sense->range_feet);
        $this->assertFalse($sense->is_limited);
        $this->assertNull($sense->notes);
    }

    #[Test]
    public function it_imports_multiple_senses_for_entity()
    {
        $monster = Monster::factory()->create();

        $sensesData = [
            [
                'type' => 'darkvision',
                'range' => 120,
                'is_limited' => false,
                'notes' => null,
            ],
            [
                'type' => 'blindsight',
                'range' => 30,
                'is_limited' => false,
                'notes' => null,
            ],
        ];

        $this->importEntitySenses($monster, $sensesData);

        $this->assertCount(2, $monster->senses);

        $darkvision = $monster->senses->firstWhere('sense.slug', 'darkvision');
        $this->assertEquals(120, $darkvision->range_feet);

        $blindsight = $monster->senses->firstWhere('sense.slug', 'blindsight');
        $this->assertEquals(30, $blindsight->range_feet);
    }

    #[Test]
    public function it_imports_sense_with_limited_flag()
    {
        $monster = Monster::factory()->create();

        $sensesData = [
            [
                'type' => 'blindsight',
                'range' => 10,
                'is_limited' => true,
                'notes' => 'blind beyond this radius',
            ],
        ];

        $this->importEntitySenses($monster, $sensesData);

        $sense = $monster->senses->first();
        $this->assertTrue($sense->is_limited);
        $this->assertEquals('blind beyond this radius', $sense->notes);
    }

    #[Test]
    public function it_imports_sense_with_notes()
    {
        $monster = Monster::factory()->create();

        $sensesData = [
            [
                'type' => 'tremorsense',
                'range' => 60,
                'is_limited' => false,
                'notes' => 'works only on stone surfaces',
            ],
        ];

        $this->importEntitySenses($monster, $sensesData);

        $sense = $monster->senses->first();
        $this->assertEquals('works only on stone surfaces', $sense->notes);
    }

    #[Test]
    public function it_clears_existing_senses_before_import()
    {
        $race = Race::factory()->create();

        // Create initial senses
        $darkvisionSense = Sense::where('slug', 'darkvision')->first();
        EntitySense::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'sense_id' => $darkvisionSense->id,
            'range_feet' => 60,
            'is_limited' => false,
        ]);
        EntitySense::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'sense_id' => Sense::where('slug', 'blindsight')->first()->id,
            'range_feet' => 30,
            'is_limited' => false,
        ]);

        $this->assertCount(2, $race->fresh()->senses);

        // Import new senses (should clear old ones)
        $sensesData = [
            [
                'type' => 'truesight',
                'range' => 120,
                'is_limited' => false,
                'notes' => null,
            ],
        ];

        $this->importEntitySenses($race, $sensesData);

        $race->refresh();
        $this->assertCount(1, $race->senses);
        $this->assertEquals('truesight', $race->senses->first()->sense->slug);
    }

    #[Test]
    public function it_handles_empty_senses_array()
    {
        $race = Race::factory()->create();

        // Create initial sense
        EntitySense::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'sense_id' => Sense::where('slug', 'darkvision')->first()->id,
            'range_feet' => 60,
            'is_limited' => false,
        ]);

        $this->assertCount(1, $race->fresh()->senses);

        // Import empty array (should clear all)
        $this->importEntitySenses($race, []);

        $this->assertCount(0, $race->fresh()->senses);
    }

    #[Test]
    public function it_skips_unknown_sense_types()
    {
        $race = Race::factory()->create();

        $sensesData = [
            [
                'type' => 'darkvision',
                'range' => 60,
                'is_limited' => false,
                'notes' => null,
            ],
            [
                'type' => 'unknown-sense-type',
                'range' => 30,
                'is_limited' => false,
                'notes' => null,
            ],
        ];

        $this->importEntitySenses($race, $sensesData);

        // Should only import the known sense type
        $this->assertCount(1, $race->senses);
        $this->assertEquals('darkvision', $race->senses->first()->sense->slug);
    }

    #[Test]
    public function it_deduplicates_senses_by_type()
    {
        $monster = Monster::factory()->create();

        // Simulate XML data quality issue with duplicate senses
        $sensesData = [
            [
                'type' => 'darkvision',
                'range' => 60,
                'is_limited' => false,
                'notes' => null,
            ],
            [
                'type' => 'darkvision',
                'range' => 60,
                'is_limited' => false,
                'notes' => null,
            ],
        ];

        $this->importEntitySenses($monster, $sensesData);

        // Should only create one sense record
        $this->assertCount(1, $monster->senses);
        $this->assertEquals('darkvision', $monster->senses->first()->sense->slug);
    }

    #[Test]
    public function it_uses_sense_cache_for_lookups()
    {
        // Clear cache first
        self::clearSenseCache();

        $race = Race::factory()->create();

        $sensesData = [
            [
                'type' => 'darkvision',
                'range' => 60,
                'is_limited' => false,
                'notes' => null,
            ],
        ];

        // First import should populate cache
        $this->importEntitySenses($race, $sensesData);

        // Verify cache is populated
        $this->assertNotNull(self::$senseCache);
        $this->assertArrayHasKey('darkvision', self::$senseCache);

        // Second import should use cache
        $monster = Monster::factory()->create();
        $this->importEntitySenses($monster, $sensesData);

        $this->assertCount(1, $monster->senses);
    }

    #[Test]
    public function it_clears_sense_cache()
    {
        // Populate cache
        $race = Race::factory()->create();
        $this->importEntitySenses($race, [
            [
                'type' => 'darkvision',
                'range' => 60,
                'is_limited' => false,
                'notes' => null,
            ],
        ]);

        $this->assertNotNull(self::$senseCache);

        // Clear cache
        self::clearSenseCache();

        $this->assertNull(self::$senseCache);
    }

    #[Test]
    public function it_handles_sense_with_zero_range()
    {
        $monster = Monster::factory()->create();

        $sensesData = [
            [
                'type' => 'blindsight',
                'range' => 0,
                'is_limited' => true,
                'notes' => 'blind',
            ],
        ];

        $this->importEntitySenses($monster, $sensesData);

        $sense = $monster->senses->first();
        $this->assertEquals(0, $sense->range_feet);
        $this->assertTrue($sense->is_limited);
    }

    #[Test]
    public function it_handles_all_sense_types()
    {
        $monster = Monster::factory()->create();

        $sensesData = [
            [
                'type' => 'darkvision',
                'range' => 60,
                'is_limited' => false,
                'notes' => null,
            ],
            [
                'type' => 'blindsight',
                'range' => 30,
                'is_limited' => false,
                'notes' => null,
            ],
            [
                'type' => 'tremorsense',
                'range' => 60,
                'is_limited' => false,
                'notes' => null,
            ],
            [
                'type' => 'truesight',
                'range' => 120,
                'is_limited' => false,
                'notes' => null,
            ],
        ];

        $this->importEntitySenses($monster, $sensesData);

        $this->assertCount(4, $monster->senses);

        $senseTypes = $monster->senses->pluck('sense.slug')->toArray();
        $this->assertContains('darkvision', $senseTypes);
        $this->assertContains('blindsight', $senseTypes);
        $this->assertContains('tremorsense', $senseTypes);
        $this->assertContains('truesight', $senseTypes);
    }

    #[Test]
    public function it_works_with_both_race_and_monster_entities()
    {
        $race = Race::factory()->create();
        $monster = Monster::factory()->create();

        $sensesData = [
            [
                'type' => 'darkvision',
                'range' => 60,
                'is_limited' => false,
                'notes' => null,
            ],
        ];

        $this->importEntitySenses($race, $sensesData);
        $this->importEntitySenses($monster, $sensesData);

        $this->assertCount(1, $race->senses);
        $this->assertCount(1, $monster->senses);

        $this->assertEquals(Race::class, $race->senses->first()->reference_type);
        $this->assertEquals(Monster::class, $monster->senses->first()->reference_type);
    }

    #[Test]
    public function it_handles_default_is_limited_value()
    {
        $race = Race::factory()->create();

        // Simulate data without is_limited field
        $sensesData = [
            [
                'type' => 'darkvision',
                'range' => 60,
                // is_limited not provided
                'notes' => null,
            ],
        ];

        $this->importEntitySenses($race, $sensesData);

        $sense = $race->senses->first();
        // Should default to false
        $this->assertFalse($sense->is_limited);
    }

    #[Test]
    public function it_handles_missing_notes_field()
    {
        $race = Race::factory()->create();

        $sensesData = [
            [
                'type' => 'darkvision',
                'range' => 60,
                'is_limited' => false,
                // notes not provided
            ],
        ];

        $this->importEntitySenses($race, $sensesData);

        $sense = $race->senses->first();
        $this->assertNull($sense->notes);
    }
}
