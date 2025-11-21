<?php

namespace Tests\Feature\Importers;

use App\Models\CharacterClass;
use App\Models\DamageType;
use App\Models\Source;
use App\Models\Spell;
use App\Models\SpellEffect;
use App\Models\SpellSchool;
use App\Services\Importers\SpellImporter;
use App\Services\Parsers\SpellXmlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpellImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_spell_from_parsed_data(): void
    {
        // Create required classes for spell associations
        CharacterClass::factory()->create(['name' => 'Wizard', 'slug' => 'wizard']);
        CharacterClass::factory()->create(['name' => 'Sorcerer', 'slug' => 'sorcerer']);

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
            'sources' => [
                ['code' => 'PHB', 'pages' => '241'],
            ],
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

        $importer = new SpellImporter;
        $spell = $importer->import($spellData);

        $this->assertInstanceOf(Spell::class, $spell);
        $this->assertEquals('Fireball', $spell->name);
        $this->assertEquals(3, $spell->level);
        $this->assertEquals($school->id, $spell->spell_school_id);
        $this->assertFalse($spell->needs_concentration);
        $this->assertFalse($spell->is_ritual);

        $this->assertDatabaseHas('spells', [
            'name' => 'Fireball',
            'level' => 3,
        ]);

        // Verify source is linked via entity_sources junction table
        $this->assertEquals(1, $spell->sources()->count());
        $entitySource = $spell->sources()->first();
        $this->assertEquals($source->id, $entitySource->source_id);
        $this->assertEquals('241', $entitySource->pages);

        $this->assertDatabaseHas('entity_sources', [
            'reference_type' => Spell::class,
            'reference_id' => $spell->id,
            'source_id' => $source->id,
            'pages' => '241',
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

        // Verify class associations are created
        $this->assertEquals(2, $spell->classes()->count());

        $classNames = $spell->classes()->pluck('name')->toArray();
        $this->assertContains('Wizard', $classNames);
        $this->assertContains('Sorcerer', $classNames);

        // Verify class_spells junction table entries
        $this->assertDatabaseHas('class_spells', [
            'spell_id' => $spell->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_parses_damage_type_from_effect_description(): void
    {
        // Seed required data
        CharacterClass::factory()->create(['name' => 'Wizard', 'slug' => 'wizard']);
        CharacterClass::factory()->create(['name' => 'Sorcerer', 'slug' => 'sorcerer']);

        $acidDamageType = DamageType::where('name', 'Acid')->first();
        $this->assertNotNull($acidDamageType, 'Acid damage type should exist in database');

        // Test data simulating Acid Splash
        $spellData = [
            'name' => 'Acid Splash',
            'level' => 0,
            'school' => 'C',
            'casting_time' => '1 action',
            'range' => '60 feet',
            'components' => 'V, S',
            'material_components' => null,
            'duration' => 'Instantaneous',
            'needs_concentration' => false,
            'is_ritual' => false,
            'description' => 'You hurl a bubble of acid...',
            'higher_levels' => null,
            'classes' => ['Wizard', 'Sorcerer'],
            'sources' => [
                ['code' => 'PHB', 'pages' => '211'],
            ],
            'effects' => [
                [
                    'effect_type' => 'damage',
                    'description' => 'Acid Damage',
                    'damage_type_name' => 'Acid', // NEW: Include damage type name for lookup
                    'dice_formula' => '1d6',
                    'base_value' => null,
                    'scaling_type' => 'character_level',
                    'min_character_level' => 0,
                    'min_spell_slot' => null,
                    'scaling_increment' => null,
                ],
            ],
        ];

        $importer = new SpellImporter;
        $spell = $importer->import($spellData);

        // Verify spell effect has damage_type_id set
        $effect = $spell->effects()->first();
        $this->assertNotNull($effect, 'Spell should have at least one effect');
        $this->assertEquals($acidDamageType->id, $effect->damage_type_id, 'Effect should have acid damage type ID');

        $this->assertDatabaseHas('spell_effects', [
            'spell_id' => $spell->id,
            'description' => 'Acid Damage',
            'damage_type_id' => $acidDamageType->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_imports_acid_splash_from_xml(): void
    {
        // Seed base classes that Acid Splash is available to
        CharacterClass::factory()->create(['name' => 'Fighter', 'slug' => 'fighter']);
        CharacterClass::factory()->create(['name' => 'Rogue', 'slug' => 'rogue']);
        CharacterClass::factory()->create(['name' => 'Sorcerer', 'slug' => 'sorcerer']);
        CharacterClass::factory()->create(['name' => 'Wizard', 'slug' => 'wizard']);

        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <spell>
    <name>Acid Splash</name>
    <level>0</level>
    <school>C</school>
    <time>1 action</time>
    <range>60 feet</range>
    <components>V, S</components>
    <duration>Instantaneous</duration>
    <classes>School: Conjuration, Fighter (Eldritch Knight), Rogue (Arcane Trickster), Sorcerer, Wizard</classes>
    <text>You hurl a bubble of acid. Choose one creature you can see within range, or choose two creatures you can see within range that are within 5 feet of each other. A target must succeed on a Dexterity saving throw or take 1d6 acid damage.

Cantrip Upgrade: This spell's damage increases by 1d6 when you reach 5th level (2d6), 11th level (3d6), and 17th level (4d6).

Source:	Player's Handbook (2014) p. 211</text>
    <roll description="Acid Damage" level="0">1d6</roll>
    <roll description="Acid Damage" level="5">2d6</roll>
    <roll description="Acid Damage" level="11">3d6</roll>
    <roll description="Acid Damage" level="17">4d6</roll>
  </spell>
</compendium>
XML;

        $parser = new SpellXmlParser;
        $parsedSpells = $parser->parse($xml);

        $importer = new SpellImporter;
        foreach ($parsedSpells as $spellData) {
            $importer->import($spellData);
        }

        $spell = Spell::where('name', 'Acid Splash')->first();
        $this->assertNotNull($spell, 'Acid Splash should be imported');

        // Check damage type is set for all effects
        $acidDamageType = DamageType::where('name', 'Acid')->first();
        $this->assertNotNull($acidDamageType);

        $effects = $spell->effects;
        $this->assertCount(4, $effects, 'Should have 4 scaling effects');

        foreach ($effects as $effect) {
            $this->assertEquals($acidDamageType->id, $effect->damage_type_id,
                "Effect '{$effect->dice_formula}' should have acid damage type");
        }

        // Check class associations
        $this->assertEquals(4, $spell->classes()->count(), 'Should have 4 class associations');

        $classNames = $spell->classes()->pluck('name')->sort()->values()->toArray();
        $this->assertEquals(['Fighter', 'Rogue', 'Sorcerer', 'Wizard'], $classNames,
            'Should have Fighter, Rogue, Sorcerer, and Wizard (subclasses stripped)');
    }
}
