<?php

namespace Tests\Feature\Importers;

use App\Models\Spell;
use App\Models\SpellSchool;
use App\Models\Source;
use App\Services\Importers\SpellImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpellImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_spell_from_parsed_data(): void
    {
        $school = SpellSchool::where('code', 'EV')->first();
        $source = Source::where('code', 'PHB')->first();

        $this->assertNotNull($school);
        $this->assertNotNull($source);

        $spellData = [
            'name' => 'Fireball',
            'level' => 3,
            'school' => 'EV',
            'casting_time' => '1 action',
            'range' => '150 feet',
            'components' => 'V, S, M',
            'material_components' => 'a tiny ball of bat guano and sulfur',
            'duration' => 'Instantaneous',
            'needs_concentration' => false,
            'is_ritual' => false,
            'description' => 'A bright streak flashes...',
            'higher_levels' => null,
            'classes' => ['Wizard', 'Sorcerer'],
            'source_code' => 'PHB',
            'source_pages' => '241',
        ];

        $importer = new SpellImporter();
        $spell = $importer->import($spellData);

        $this->assertInstanceOf(Spell::class, $spell);
        $this->assertEquals('Fireball', $spell->name);
        $this->assertEquals(3, $spell->level);
        $this->assertEquals($school->id, $spell->spell_school_id);
        $this->assertEquals($source->id, $spell->source_id);
        $this->assertEquals('241', $spell->source_pages);
        $this->assertFalse($spell->needs_concentration);
        $this->assertFalse($spell->is_ritual);

        $this->assertDatabaseHas('spells', [
            'name' => 'Fireball',
            'level' => 3,
            'source_id' => $source->id,
        ]);
    }
}
