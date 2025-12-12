<?php

namespace Tests\Unit\Models;

use App\Models\EntitySource;
use App\Models\Source;
use App\Models\Spell;
use App\Models\SpellSchool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-db')]
class SpellSearchableTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_searchable_array_with_denormalized_data(): void
    {
        $school = SpellSchool::firstOrCreate(['code' => 'EV'], ['name' => 'Evocation']);
        $source = Source::firstOrCreate(
            ['code' => 'PHB'],
            [
                'name' => 'Player\'s Handbook',
                'publication_year' => 2014,
                'edition' => '5e',
            ]
        );

        $spell = Spell::factory()->create([
            'name' => 'Fireball',
            'level' => 3,
            'spell_school_id' => $school->id,
            'casting_time' => '1 action',
            'range' => '150 feet',
            'components' => 'V, S, M',
            'duration' => 'Instantaneous',
            'description' => 'A bright streak flashes from your pointing finger...',
            'needs_concentration' => false,
            'is_ritual' => false,
        ]);

        EntitySource::create([
            'reference_type' => Spell::class,
            'reference_id' => $spell->id,
            'source_id' => $source->id,
            'pages' => '241',
        ]);
        $spell->refresh();

        $searchable = $spell->toSearchableArray();

        $this->assertArrayHasKey('id', $searchable);
        $this->assertEquals('Fireball', $searchable['name']);
        $this->assertEquals(3, $searchable['level']);
        $this->assertEquals('Evocation', $searchable['school_name']);
        $this->assertEquals('EV', $searchable['school_code']);
        $this->assertArrayHasKey('description', $searchable);
        $this->assertEquals(['Player\'s Handbook'], $searchable['sources']);
        $this->assertEquals(['PHB'], $searchable['source_codes']);
        $this->assertArrayHasKey('classes', $searchable);
        $this->assertIsArray($searchable['classes']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_defines_searchable_relationships(): void
    {
        $spell = new Spell;

        $this->assertIsArray($spell->searchableWith());
        $this->assertContains('spellSchool', $spell->searchableWith());
        $this->assertContains('sources', $spell->searchableWith());
        $this->assertContains('classes', $spell->searchableWith());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_uses_correct_search_index_name(): void
    {
        $spell = new Spell;

        // In testing environment, Scout adds 'test_' prefix (see .env.testing SCOUT_PREFIX)
        $this->assertEquals('test_spells', $spell->searchableAs());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_material_cost_fields_in_searchable_array(): void
    {
        // material_cost_gp and material_consumed are now real columns,
        // not computed from material_components text
        $spell = Spell::factory()->create([
            'name' => 'Arcane Lock',
            'material_components' => 'gold dust worth at least 25 gp, which the spell consumes',
            'material_cost_gp' => 25,
            'material_consumed' => true,
        ]);

        $searchable = $spell->toSearchableArray();

        $this->assertArrayHasKey('material_cost_gp', $searchable);
        $this->assertEquals(25, $searchable['material_cost_gp']);
        $this->assertArrayHasKey('material_consumed', $searchable);
        $this->assertTrue($searchable['material_consumed']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_aoe_fields_in_searchable_array(): void
    {
        $spell = Spell::factory()->create([
            'name' => 'Fireball',
            'description' => 'Each creature in a 20-foot-radius sphere must make a Dexterity saving throw.',
        ]);

        $searchable = $spell->toSearchableArray();

        $this->assertArrayHasKey('aoe_type', $searchable);
        $this->assertEquals('sphere', $searchable['aoe_type']);
        $this->assertArrayHasKey('aoe_size', $searchable);
        $this->assertEquals(20, $searchable['aoe_size']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_new_fields_in_filterable_attributes(): void
    {
        $spell = new Spell;

        $options = $spell->searchableOptions();

        $this->assertContains('material_cost_gp', $options['filterableAttributes']);
        $this->assertContains('material_consumed', $options['filterableAttributes']);
        $this->assertContains('aoe_type', $options['filterableAttributes']);
        $this->assertContains('aoe_size', $options['filterableAttributes']);
    }
}
