<?php

namespace Tests\Feature\Models;

use App\Models\EntitySource;
use App\Models\Source;
use App\Models\Spell;
use App\Models\SpellSchool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntitySourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_entity_source_belongs_to_source(): void
    {
        $source = Source::where('code', 'PHB')->first();
        $school = SpellSchool::first();

        $spell = Spell::create([
            'name' => 'Test Spell',
            'level' => 1,
            'spell_school_id' => $school->id,
            'casting_time' => '1 action',
            'range' => 'Touch',
            'components' => 'V, S',
            'duration' => 'Instantaneous',
            'needs_concentration' => false,
            'is_ritual' => false,
            'description' => 'Test description',
            'source_id' => $source->id,
            'source_pages' => '100',
        ]);

        $entitySource = EntitySource::create([
            'entity_type' => 'spell',
            'entity_id' => $spell->id,
            'source_id' => $source->id,
            'pages' => '150',
        ]);

        $this->assertInstanceOf(Source::class, $entitySource->source);
        $this->assertEquals('PHB', $entitySource->source->code);
    }

    public function test_entity_source_has_polymorphic_reference(): void
    {
        $source = Source::where('code', 'PHB')->first();
        $school = SpellSchool::first();

        $spell = Spell::create([
            'name' => 'Test Spell',
            'level' => 1,
            'spell_school_id' => $school->id,
            'casting_time' => '1 action',
            'range' => 'Touch',
            'components' => 'V, S',
            'duration' => 'Instantaneous',
            'needs_concentration' => false,
            'is_ritual' => false,
            'description' => 'Test description',
            'source_id' => $source->id,
            'source_pages' => '100',
        ]);

        $entitySource = EntitySource::create([
            'entity_type' => 'spell',
            'entity_id' => $spell->id,
            'source_id' => $source->id,
            'pages' => '150',
        ]);

        // Polymorphic relationship
        $this->assertEquals('spell', $entitySource->entity_type);
        $this->assertEquals($spell->id, $entitySource->entity_id);
    }

    public function test_entity_source_does_not_use_timestamps(): void
    {
        $entitySource = new EntitySource();
        $this->assertFalse($entitySource->timestamps);
    }
}
