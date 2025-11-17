<?php

namespace Tests\Feature\Importers;

use App\Models\Spell;
use App\Models\SpellEffect;
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
            'effects' => [
                [
                    'effect_type' => 'damage',
                    'description' => 'Fire damage',
                    'dice_formula' => '8d6',
                    'base_value' => null,
                    'scaling_type' => 'spell_slot',
                    'min_character_level' => null,
                    'min_spell_slot' => 3,
                    'scaling_increment' => '1d6',
                ],
            ],
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

        // Verify spell effects are imported
        $this->assertEquals(1, $spell->effects()->count());

        $effect = $spell->effects()->first();
        $this->assertInstanceOf(SpellEffect::class, $effect);
        $this->assertEquals('damage', $effect->effect_type);
        $this->assertEquals('Fire damage', $effect->description);
        $this->assertEquals('8d6', $effect->dice_formula);
        $this->assertEquals('spell_slot', $effect->scaling_type);
        $this->assertEquals(3, $effect->min_spell_slot);
        $this->assertEquals('1d6', $effect->scaling_increment);

        $this->assertDatabaseHas('spell_effects', [
            'spell_id' => $spell->id,
            'effect_type' => 'damage',
            'description' => 'Fire damage',
            'dice_formula' => '8d6',
        ]);
    }
}
