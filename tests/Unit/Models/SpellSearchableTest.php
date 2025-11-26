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
        $school = SpellSchool::factory()->create(['name' => 'Evocation', 'code' => 'EVO']);
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
        $this->assertEquals('EVO', $searchable['school_code']);
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
}
