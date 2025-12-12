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
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('importers')]
class SpellImporterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function imports_spell_from_parsed_data(): void
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
        // Seed base classes
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'slug' => 'fighter']);
        $rogue = CharacterClass::factory()->create(['name' => 'Rogue', 'slug' => 'rogue']);
        CharacterClass::factory()->create(['name' => 'Sorcerer', 'slug' => 'sorcerer']);
        CharacterClass::factory()->create(['name' => 'Wizard', 'slug' => 'wizard']);

        // Seed subclasses (required for subclass-specific spells)
        CharacterClass::factory()->create([
            'name' => 'Eldritch Knight',
            'slug' => 'eldritch-knight',
            'parent_class_id' => $fighter->id,
        ]);
        CharacterClass::factory()->create([
            'name' => 'Arcane Trickster',
            'slug' => 'arcane-trickster',
            'parent_class_id' => $rogue->id,
        ]);

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

        // Check class associations - should use SUBCLASSES when specified in parentheses
        $this->assertEquals(4, $spell->classes()->count(), 'Should have 4 class associations');

        $classNames = $spell->classes()->pluck('name')->sort()->values()->toArray();
        $this->assertEquals(['Arcane Trickster', 'Eldritch Knight', 'Sorcerer', 'Wizard'], $classNames,
            'Should use subclasses when specified: Eldritch Knight (not Fighter), Arcane Trickster (not Rogue)');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_uses_base_class_when_no_subclass_specified(): void
    {
        // Seed base classes
        CharacterClass::factory()->create(['name' => 'Wizard', 'slug' => 'wizard']);
        CharacterClass::factory()->create(['name' => 'Sorcerer', 'slug' => 'sorcerer']);

        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <spell>
    <name>Magic Missile</name>
    <level>1</level>
    <school>EV</school>
    <time>1 action</time>
    <range>120 feet</range>
    <components>V, S</components>
    <duration>Instantaneous</duration>
    <classes>School: Evocation, Wizard, Sorcerer</classes>
    <text>Test spell</text>
  </spell>
</compendium>
XML;

        $parser = new SpellXmlParser;
        $parsedSpells = $parser->parse($xml);

        $importer = new SpellImporter;
        foreach ($parsedSpells as $spellData) {
            $importer->import($spellData);
        }

        $spell = Spell::where('name', 'Magic Missile')->first();
        $this->assertNotNull($spell);

        // Should use BASE classes when no subclass specified
        $this->assertEquals(2, $spell->classes()->count());
        $classNames = $spell->classes()->pluck('name')->sort()->values()->toArray();
        $this->assertEquals(['Sorcerer', 'Wizard'], $classNames,
            'Should use base classes when no parentheses present');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_imports_sleep_spell_with_archfey_and_higher_levels(): void
    {
        // Seed base classes
        $rogue = CharacterClass::factory()->create(['name' => 'Rogue', 'slug' => 'rogue']);
        $warlock = CharacterClass::factory()->create(['name' => 'Warlock', 'slug' => 'warlock']);
        CharacterClass::factory()->create(['name' => 'Bard', 'slug' => 'bard']);
        CharacterClass::factory()->create(['name' => 'Sorcerer', 'slug' => 'sorcerer']);
        CharacterClass::factory()->create(['name' => 'Wizard', 'slug' => 'wizard']);

        // Seed subclasses (note: database has "The Archfey", XML has "Archfey")
        CharacterClass::factory()->create([
            'name' => 'Arcane Trickster',
            'slug' => 'arcane-trickster',
            'parent_class_id' => $rogue->id,
        ]);
        CharacterClass::factory()->create([
            'name' => 'The Archfey',
            'slug' => 'the-archfey',
            'parent_class_id' => $warlock->id,
        ]);

        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <spell>
    <name>Sleep</name>
    <level>1</level>
    <school>EN</school>
    <time>1 action</time>
    <range>90 feet</range>
    <components>V, S, M (a pinch of fine sand, rose petals, or a cricket)</components>
    <duration>1 minute</duration>
    <classes>School: Enchantment, Rogue (Arcane Trickster), Bard, Sorcerer, Wizard, Warlock (Archfey)</classes>
    <text>This spell sends creatures into a magical slumber. Roll 5d8; the total is how many hit points of creatures this spell can affect.

At Higher Levels: When you cast this spell using a spell slot of 2nd level or higher, roll an additional 2d8 for each slot level above 1st.

Source:	Player's Handbook (2014) p. 276</text>
    <roll description="Hit Points" level="1">5d8</roll>
  </spell>
</compendium>
XML;

        $parser = new SpellXmlParser;
        $parsedSpells = $parser->parse($xml);

        $importer = new SpellImporter;
        foreach ($parsedSpells as $spellData) {
            $importer->import($spellData);
        }

        $spell = Spell::where('name', 'Sleep')->with('classes')->first();
        $this->assertNotNull($spell);

        // Issue #1: Should parse "At Higher Levels" section
        $this->assertNotNull($spell->higher_levels, 'higher_levels should not be null');
        $this->assertStringContainsString('2nd level or higher', $spell->higher_levels);
        $this->assertStringContainsString('2d8', $spell->higher_levels);

        // Issue #2: Should match "Archfey" to "The Archfey" subclass
        $this->assertEquals(5, $spell->classes()->count(), 'Should have 5 class associations');

        $classNames = $spell->classes->pluck('name')->sort()->values()->toArray();
        $this->assertContains('The Archfey', $classNames, 'Should match "Archfey" to "The Archfey"');
        $this->assertContains('Arcane Trickster', $classNames);
        $this->assertContains('Bard', $classNames);
        $this->assertContains('Sorcerer', $classNames);
        $this->assertContains('Wizard', $classNames);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_maps_druid_coast_to_circle_of_the_land(): void
    {
        // Seed base classes
        $druid = CharacterClass::factory()->create(['name' => 'Druid', 'slug' => 'druid']);
        $paladin = CharacterClass::factory()->create(['name' => 'Paladin', 'slug' => 'paladin']);
        CharacterClass::factory()->create(['name' => 'Sorcerer', 'slug' => 'sorcerer']);
        CharacterClass::factory()->create(['name' => 'Warlock', 'slug' => 'warlock']);
        CharacterClass::factory()->create(['name' => 'Wizard', 'slug' => 'wizard']);

        // Seed subclasses
        CharacterClass::factory()->create([
            'name' => 'Circle of the Land',
            'slug' => 'circle-of-the-land',
            'parent_class_id' => $druid->id,
        ]);
        CharacterClass::factory()->create([
            'name' => 'Oath of the Ancients',
            'slug' => 'oath-of-the-ancients',
            'parent_class_id' => $paladin->id,
        ]);
        CharacterClass::factory()->create([
            'name' => 'Oath of Vengeance',
            'slug' => 'oath-of-vengeance',
            'parent_class_id' => $paladin->id,
        ]);

        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <spell>
    <name>Misty Step</name>
    <level>2</level>
    <school>C</school>
    <time>1 bonus action</time>
    <range>Self</range>
    <components>V</components>
    <duration>Instantaneous</duration>
    <classes>School: Conjuration, Sorcerer, Warlock, Wizard, Druid (Coast), Paladin (Ancients), Paladin (Vengeance)</classes>
    <text>Briefly surrounded by silvery mist, you teleport up to 30 feet to an unoccupied space that you can see.

Source:	Player's Handbook (2014) p. 260</text>
  </spell>
</compendium>
XML;

        $parser = new SpellXmlParser;
        $parsedSpells = $parser->parse($xml);

        $importer = new SpellImporter;
        foreach ($parsedSpells as $spellData) {
            $importer->import($spellData);
        }

        $spell = Spell::where('name', 'Misty Step')->with('classes')->first();
        $this->assertNotNull($spell);

        // Should map "Coast" to "Circle of the Land"
        $this->assertEquals(6, $spell->classes()->count(), 'Should have 6 class associations');

        $classNames = $spell->classes->pluck('name')->sort()->values()->toArray();
        $this->assertContains('Circle of the Land', $classNames, 'Should map "Druid (Coast)" to "Circle of the Land"');
        $this->assertContains('Oath of the Ancients', $classNames);
        $this->assertContains('Oath of Vengeance', $classNames);
        $this->assertContains('Sorcerer', $classNames);
        $this->assertContains('Warlock', $classNames);
        $this->assertContains('Wizard', $classNames);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_extracts_tags_from_classes_field(): void
    {
        // Seed base class
        CharacterClass::factory()->create(['name' => 'Wizard', 'slug' => 'wizard']);

        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <spell>
    <name>Simulacrum</name>
    <level>7</level>
    <school>I</school>
    <time>12 hours</time>
    <range>Touch</range>
    <components>V, S, M</components>
    <duration>Until dispelled</duration>
    <classes>School: Illusion, Touch Spells, Wizard</classes>
    <text>You shape an illusory duplicate of one beast or humanoid...

Source:	Player's Handbook (2014) p. 276</text>
  </spell>
</compendium>
XML;

        $parser = new SpellXmlParser;
        $parsedSpells = $parser->parse($xml);

        $importer = new SpellImporter;
        foreach ($parsedSpells as $spellData) {
            $importer->import($spellData);
        }

        $spell = Spell::where('name', 'Simulacrum')->first();
        $this->assertNotNull($spell);

        // Should have Wizard class
        $this->assertEquals(1, $spell->classes()->count());
        $this->assertEquals('Wizard', $spell->classes->first()->name);

        // Should have "Touch Spells" tag
        $this->assertCount(1, $spell->tags);
        $this->assertEquals('Touch Spells', $spell->tags->first()->name);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_imports_scaling_increment_for_fireball(): void
    {
        // Create required base classes
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'slug' => 'fighter']);
        $cleric = CharacterClass::factory()->create(['name' => 'Cleric', 'slug' => 'cleric']);
        $warlock = CharacterClass::factory()->create(['name' => 'Warlock', 'slug' => 'warlock']);
        CharacterClass::factory()->create(['name' => 'Sorcerer', 'slug' => 'sorcerer']);
        CharacterClass::factory()->create(['name' => 'Wizard', 'slug' => 'wizard']);

        // Create required subclasses
        CharacterClass::factory()->create([
            'name' => 'Eldritch Knight',
            'slug' => 'eldritch-knight',
            'parent_class_id' => $fighter->id,
        ]);
        CharacterClass::factory()->create([
            'name' => 'Light Domain',
            'slug' => 'light-domain',
            'parent_class_id' => $cleric->id,
        ]);
        CharacterClass::factory()->create([
            'name' => 'The Fiend',
            'slug' => 'the-fiend',
            'parent_class_id' => $warlock->id,
        ]);

        // This test uses actual XML import from PHB
        $xmlPath = config('import.xml_source_path').'/'.config('import.source_directories.phb').'/spells-phb.xml';

        $this->artisan('import:spells', ['file' => $xmlPath])
            ->assertSuccessful();

        $fireball = Spell::where('slug', 'phb:fireball')->first();

        $this->assertNotNull($fireball, 'Fireball spell should exist');
        $this->assertNotNull($fireball->higher_levels, 'Fireball should have higher_levels text');

        $damageEffect = $fireball->effects->firstWhere('effect_type', 'damage');

        $this->assertNotNull($damageEffect, 'Fireball should have a damage effect');
        $this->assertEquals('1d6', $damageEffect->scaling_increment);
    }
}
