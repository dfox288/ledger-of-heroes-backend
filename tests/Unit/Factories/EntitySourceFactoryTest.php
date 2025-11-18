<?php

namespace Tests\Unit\Factories;

use App\Models\EntitySource;
use App\Models\Race;
use App\Models\Source;
use App\Models\Spell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntitySourceFactoryTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_creates_an_entity_source_with_valid_data()
    {
        $entitySource = EntitySource::factory()->create();

        $this->assertInstanceOf(EntitySource::class, $entitySource);
        $this->assertNotNull($entitySource->reference_type);
        $this->assertNotNull($entitySource->reference_id);
        $this->assertNotNull($entitySource->source_id);
    }

    /** @test */
    public function it_creates_entity_source_for_specific_entity()
    {
        $race = Race::factory()->create();
        $entitySource = EntitySource::factory()
            ->forEntity(Race::class, $race->id)
            ->create();

        $this->assertEquals(Race::class, $entitySource->reference_type);
        $this->assertEquals($race->id, $entitySource->reference_id);
    }

    /** @test */
    public function it_creates_entity_source_from_specific_source()
    {
        $entitySource = EntitySource::factory()
            ->fromSource('PHB')
            ->create();

        $this->assertEquals('PHB', $entitySource->source->code);
    }
}
