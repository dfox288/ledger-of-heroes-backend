<?php

namespace Tests\Feature\Models;

use App\Models\EntitySource;
use App\Models\Source;
use App\Models\Spell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class EntitySourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function entity_source_belongs_to_source(): void
    {
        $source = Source::where('code', 'PHB')->first();

        $spell = Spell::factory()->create([
            'name' => 'Test Spell',
        ]);

        $entitySource = EntitySource::factory()->forEntity(Spell::class, $spell->id)->create([
            'source_id' => $source->id,
            'pages' => '150',
        ]);

        // Test the EntitySource -> Source relationship
        $this->assertInstanceOf(Source::class, $entitySource->source);
        $this->assertEquals('PHB', $entitySource->source->code);

        // Test the Spell -> EntitySources relationship (renamed to 'sources')
        $spell->refresh();
        $this->assertCount(1, $spell->sources);
        $this->assertInstanceOf(EntitySource::class, $spell->sources->first());
    }

    #[Test]
    public function entity_source_has_polymorphic_reference(): void
    {
        $source = Source::where('code', 'PHB')->first();

        $spell = Spell::factory()->create([
            'name' => 'Test Spell',
        ]);

        $entitySource = EntitySource::factory()->forEntity(Spell::class, $spell->id)->create([
            'source_id' => $source->id,
            'pages' => '150',
        ]);

        // Polymorphic relationship
        $this->assertEquals(Spell::class, $entitySource->reference_type);
        $this->assertEquals($spell->id, $entitySource->reference_id);
    }

    #[Test]
    public function entity_source_does_not_use_timestamps(): void
    {
        $entitySource = new EntitySource;
        $this->assertFalse($entitySource->timestamps);
    }
}
