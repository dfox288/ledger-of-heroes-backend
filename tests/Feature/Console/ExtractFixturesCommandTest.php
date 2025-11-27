<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ExtractFixturesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Clean up test fixture directory
        $testPath = base_path('tests/fixtures/test-output');
        if (File::isDirectory($testPath)) {
            File::deleteDirectory($testPath);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_has_the_command_registered(): void
    {
        $this->artisan('fixtures:extract', ['--help' => true])
            ->assertSuccessful();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_requires_entity_type_argument(): void
    {
        try {
            $this->artisan('fixtures:extract');
            $this->fail('Expected RuntimeException was not thrown');
        } catch (\Symfony\Component\Console\Exception\RuntimeException $e) {
            $this->assertStringContainsString('Not enough arguments', $e->getMessage());
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_extracts_spells_with_coverage_based_selection(): void
    {
        // Create test spells covering edge cases
        $source = \App\Models\Source::factory()->create(['code' => 'TEST', 'name' => 'Test Source']);
        $school = \App\Models\SpellSchool::first();
        $class = \App\Models\CharacterClass::factory()->create(['slug' => 'wizard', 'name' => 'Wizard']);

        // Create spells at different levels
        foreach (range(0, 3) as $level) {
            $spell = \App\Models\Spell::factory()->create([
                'level' => $level,
                'spell_school_id' => $school->id,
            ]);
            $spell->classes()->attach($class->id);

            // Create entity source relationship
            \App\Models\EntitySource::create([
                'reference_type' => 'App\Models\Spell',
                'reference_id' => $spell->id,
                'source_id' => $source->id,
                'pages' => '100',
            ]);
        }

        // Extract
        $this->artisan('fixtures:extract', [
            'entity' => 'spells',
            '--output' => 'tests/fixtures/test-output',
        ])->assertSuccessful();

        // Verify JSON created
        $path = base_path('tests/fixtures/test-output/entities/spells.json');
        $this->assertFileExists($path);

        $data = json_decode(File::get($path), true);
        $this->assertIsArray($data);
        $this->assertGreaterThanOrEqual(4, count($data));

        // Verify structure
        $spell = $data[0];
        $this->assertArrayHasKey('name', $spell);
        $this->assertArrayHasKey('slug', $spell);
        $this->assertArrayHasKey('level', $spell);
        $this->assertArrayHasKey('school', $spell);
        $this->assertArrayHasKey('classes', $spell);
        $this->assertArrayHasKey('sources', $spell);

        // Verify relationships are slugs, not IDs
        $this->assertIsString($spell['school']);
        $this->assertIsArray($spell['classes']);
        $this->assertIsArray($spell['sources']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_extracts_monsters_with_cr_coverage(): void
    {
        $source = \App\Models\Source::factory()->create(['code' => 'TEST-MM']);

        // Create monsters at different CRs
        foreach ([0, 0.125, 0.25, 0.5, 1, 5, 10, 20] as $cr) {
            $monster = \App\Models\Monster::factory()->create([
                'challenge_rating' => $cr,
            ]);

            // Create entity source relationship
            \App\Models\EntitySource::create([
                'reference_type' => 'App\Models\Monster',
                'reference_id' => $monster->id,
                'source_id' => $source->id,
                'pages' => '100',
            ]);
        }

        $this->artisan('fixtures:extract', [
            'entity' => 'monsters',
            '--output' => 'tests/fixtures/test-output',
        ])->assertSuccessful();

        $path = base_path('tests/fixtures/test-output/entities/monsters.json');
        $this->assertFileExists($path);

        $data = json_decode(File::get($path), true);
        $this->assertGreaterThanOrEqual(8, count($data));

        // Verify structure
        $monster = $data[0];
        $this->assertArrayHasKey('name', $monster);
        $this->assertArrayHasKey('slug', $monster);
        $this->assertArrayHasKey('challenge_rating', $monster);
        $this->assertArrayHasKey('size', $monster);
        $this->assertArrayHasKey('type', $monster);
        $this->assertArrayHasKey('source', $monster);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_extracts_classes_with_all_base_classes(): void
    {
        $source = \App\Models\Source::factory()->create(['code' => 'TEST-PHB']);

        // Create test classes covering different hit dice and spellcasting
        $wizInt = \App\Models\AbilityScore::where('code', 'INT')->first();
        $wisWis = \App\Models\AbilityScore::where('code', 'WIS')->first();

        // Base class with spellcasting
        $wizard = \App\Models\CharacterClass::factory()->create([
            'name' => 'Wizard',
            'slug' => 'wizard',
            'hit_die' => 6,
            'primary_ability' => 'Intelligence',
            'spellcasting_ability_id' => $wizInt?->id,
            'parent_class_id' => null,
        ]);

        // Base class without spellcasting
        $fighter = \App\Models\CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter',
            'hit_die' => 10,
            'primary_ability' => 'Strength or Dexterity',
            'spellcasting_ability_id' => null,
            'parent_class_id' => null,
        ]);

        // Base class with different spellcasting
        $cleric = \App\Models\CharacterClass::factory()->create([
            'name' => 'Cleric',
            'slug' => 'cleric',
            'hit_die' => 8,
            'primary_ability' => 'Wisdom',
            'spellcasting_ability_id' => $wisWis?->id,
            'parent_class_id' => null,
        ]);

        // Create entity source relationships
        foreach ([$wizard, $fighter, $cleric] as $class) {
            \App\Models\EntitySource::create([
                'reference_type' => 'App\Models\CharacterClass',
                'reference_id' => $class->id,
                'source_id' => $source->id,
                'pages' => '100',
            ]);
        }

        // Extract
        $this->artisan('fixtures:extract', [
            'entity' => 'classes',
            '--output' => 'tests/fixtures/test-output',
        ])->assertSuccessful();

        // Verify JSON created
        $path = base_path('tests/fixtures/test-output/entities/classes.json');
        $this->assertFileExists($path);

        $data = json_decode(File::get($path), true);
        $this->assertIsArray($data);
        $this->assertGreaterThanOrEqual(3, count($data));

        // Verify structure
        $class = $data[0];
        $this->assertArrayHasKey('name', $class);
        $this->assertArrayHasKey('slug', $class);
        $this->assertArrayHasKey('hit_die', $class);
        $this->assertArrayHasKey('primary_ability', $class);
        $this->assertArrayHasKey('spellcasting_ability', $class);
        $this->assertArrayHasKey('source', $class);

        // Verify relationships are codes/slugs, not IDs
        $this->assertIsInt($class['hit_die']);
        $this->assertIsString($class['slug']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_extracts_races_with_size_coverage(): void
    {
        $source = \App\Models\Source::factory()->create(['code' => 'TEST-PHB']);

        // Get different sizes
        $sizeMedium = \App\Models\Size::where('code', 'M')->first();
        $sizeSmall = \App\Models\Size::where('code', 'S')->first();
        $sizeTiny = \App\Models\Size::where('code', 'T')->first();

        // Get ability scores for modifiers
        $strAbility = \App\Models\AbilityScore::where('code', 'STR')->first();
        $dexAbility = \App\Models\AbilityScore::where('code', 'DEX')->first();
        $conAbility = \App\Models\AbilityScore::where('code', 'CON')->first();

        // Create base race with traits and modifiers
        $human = \App\Models\Race::factory()->create([
            'name' => 'Human',
            'slug' => 'human',
            'size_id' => $sizeMedium->id,
            'speed' => 30,
            'parent_race_id' => null,
        ]);

        // Add ability score modifiers
        \App\Models\Modifier::create([
            'reference_type' => 'App\Models\Race',
            'reference_id' => $human->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $strAbility->id,
            'value' => 1,
        ]);

        \App\Models\Modifier::create([
            'reference_type' => 'App\Models\Race',
            'reference_id' => $human->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $dexAbility->id,
            'value' => 1,
        ]);

        // Add a trait
        \App\Models\CharacterTrait::create([
            'reference_type' => 'App\Models\Race',
            'reference_id' => $human->id,
            'name' => 'Extra Language',
            'category' => 'racial',
            'description' => 'You can speak, read, and write one extra language.',
        ]);

        // Create race with subraces
        $elf = \App\Models\Race::factory()->create([
            'name' => 'Elf',
            'slug' => 'elf',
            'size_id' => $sizeMedium->id,
            'speed' => 30,
            'parent_race_id' => null,
        ]);

        \App\Models\Modifier::create([
            'reference_type' => 'App\Models\Race',
            'reference_id' => $elf->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $dexAbility->id,
            'value' => 2,
        ]);

        // Create subrace
        $highElf = \App\Models\Race::factory()->create([
            'name' => 'High Elf',
            'slug' => 'high-elf',
            'size_id' => $sizeMedium->id,
            'speed' => 30,
            'parent_race_id' => $elf->id,
        ]);

        \App\Models\Modifier::create([
            'reference_type' => 'App\Models\Race',
            'reference_id' => $highElf->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $strAbility->id,
            'value' => 1,
        ]);

        // Create small race
        $halfling = \App\Models\Race::factory()->create([
            'name' => 'Halfling',
            'slug' => 'halfling',
            'size_id' => $sizeSmall->id,
            'speed' => 25,
            'parent_race_id' => null,
        ]);

        \App\Models\Modifier::create([
            'reference_type' => 'App\Models\Race',
            'reference_id' => $halfling->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $dexAbility->id,
            'value' => 2,
        ]);

        // Create tiny race
        $fairy = \App\Models\Race::factory()->create([
            'name' => 'Fairy',
            'slug' => 'fairy',
            'size_id' => $sizeTiny->id,
            'speed' => 30,
            'parent_race_id' => null,
        ]);

        \App\Models\Modifier::create([
            'reference_type' => 'App\Models\Race',
            'reference_id' => $fairy->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $conAbility->id,
            'value' => 1,
        ]);

        // Create entity source relationships
        foreach ([$human, $elf, $highElf, $halfling, $fairy] as $race) {
            \App\Models\EntitySource::create([
                'reference_type' => 'App\Models\Race',
                'reference_id' => $race->id,
                'source_id' => $source->id,
                'pages' => '100',
            ]);
        }

        // Extract
        $this->artisan('fixtures:extract', [
            'entity' => 'races',
            '--output' => 'tests/fixtures/test-output',
        ])->assertSuccessful();

        // Verify JSON created
        $path = base_path('tests/fixtures/test-output/entities/races.json');
        $this->assertFileExists($path);

        $data = json_decode(File::get($path), true);
        $this->assertIsArray($data);
        $this->assertGreaterThanOrEqual(4, count($data), 'Should extract at least 4 races');

        // Verify structure
        $race = $data[0];
        $this->assertArrayHasKey('name', $race);
        $this->assertArrayHasKey('slug', $race);
        $this->assertArrayHasKey('size', $race);
        $this->assertArrayHasKey('speed', $race);
        $this->assertArrayHasKey('parent_race_slug', $race);
        $this->assertArrayHasKey('ability_bonuses', $race);
        $this->assertArrayHasKey('traits', $race);
        $this->assertArrayHasKey('source', $race);

        // Verify relationships are codes/slugs, not IDs
        $this->assertIsString($race['size']);
        $this->assertIsArray($race['ability_bonuses']);
        $this->assertIsArray($race['traits']);

        // Verify we have races with different sizes
        $sizes = collect($data)->pluck('size')->unique();
        $this->assertGreaterThanOrEqual(2, $sizes->count(), 'Should have at least 2 different sizes');

        // Verify we have at least one subrace
        $subraces = collect($data)->filter(fn ($r) => $r['parent_race_slug'] !== null);
        $this->assertGreaterThanOrEqual(1, $subraces->count(), 'Should have at least one subrace');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_extracts_items_with_rarity_and_type_coverage(): void
    {
        $source = \App\Models\Source::factory()->create(['code' => 'TEST-DMG']);

        // Get different item types
        $typeGeneral = \App\Models\ItemType::where('code', 'G')->first();
        $typeMelee = \App\Models\ItemType::where('code', 'M')->first();
        $typeRanged = \App\Models\ItemType::where('code', 'R')->first();
        $typeLightArmor = \App\Models\ItemType::where('code', 'LA')->first();
        $typePotion = \App\Models\ItemType::where('code', 'P')->first();

        // Create mundane items with different types
        $rope = \App\Models\Item::factory()->create([
            'name' => 'Rope, Hempen',
            'slug' => 'rope-hempen',
            'item_type_id' => $typeGeneral->id,
            'rarity' => 'common',
            'is_magic' => false,
            'cost_cp' => 100,
            'weight' => 10.00,
        ]);

        $longsword = \App\Models\Item::factory()->create([
            'name' => 'Longsword',
            'slug' => 'longsword',
            'item_type_id' => $typeMelee->id,
            'rarity' => 'common',
            'is_magic' => false,
            'damage_dice' => '1d8',
            'versatile_damage' => '1d10',
            'cost_cp' => 1500,
            'weight' => 3.00,
        ]);

        $longbow = \App\Models\Item::factory()->create([
            'name' => 'Longbow',
            'slug' => 'longbow',
            'item_type_id' => $typeRanged->id,
            'rarity' => 'common',
            'is_magic' => false,
            'damage_dice' => '1d8',
            'range_normal' => 150,
            'range_long' => 600,
            'cost_cp' => 5000,
            'weight' => 2.00,
        ]);

        $leatherArmor = \App\Models\Item::factory()->create([
            'name' => 'Leather Armor',
            'slug' => 'leather-armor',
            'item_type_id' => $typeLightArmor->id,
            'rarity' => 'common',
            'is_magic' => false,
            'armor_class' => 11,
            'cost_cp' => 1000,
            'weight' => 10.00,
        ]);

        // Create magical items with different rarities
        $potionHealing = \App\Models\Item::factory()->create([
            'name' => 'Potion of Healing',
            'slug' => 'potion-of-healing',
            'item_type_id' => $typePotion->id,
            'rarity' => 'common',
            'is_magic' => true,
            'cost_cp' => 5000,
            'weight' => 0.50,
        ]);

        $bagOfHolding = \App\Models\Item::factory()->create([
            'name' => 'Bag of Holding',
            'slug' => 'bag-of-holding',
            'item_type_id' => $typeGeneral->id,
            'rarity' => 'uncommon',
            'is_magic' => true,
            'requires_attunement' => false,
            'cost_cp' => 0,
            'weight' => 15.00,
        ]);

        $flametongueSword = \App\Models\Item::factory()->create([
            'name' => 'Flametongue Sword',
            'slug' => 'flametongue-sword',
            'item_type_id' => $typeMelee->id,
            'rarity' => 'rare',
            'is_magic' => true,
            'requires_attunement' => true,
            'damage_dice' => '1d8',
            'cost_cp' => 0,
            'weight' => 3.00,
        ]);

        $veryRareItem = \App\Models\Item::factory()->create([
            'name' => 'Cloak of Invisibility',
            'slug' => 'cloak-of-invisibility',
            'item_type_id' => $typeGeneral->id,
            'rarity' => 'very rare',
            'is_magic' => true,
            'requires_attunement' => true,
            'cost_cp' => 0,
            'weight' => 1.00,
        ]);

        $legendaryItem = \App\Models\Item::factory()->create([
            'name' => 'Vorpal Sword',
            'slug' => 'vorpal-sword',
            'item_type_id' => $typeMelee->id,
            'rarity' => 'legendary',
            'is_magic' => true,
            'requires_attunement' => true,
            'damage_dice' => '1d8',
            'cost_cp' => 0,
            'weight' => 3.00,
        ]);

        // Create entity source relationships
        foreach ([$rope, $longsword, $longbow, $leatherArmor, $potionHealing, $bagOfHolding, $flametongueSword, $veryRareItem, $legendaryItem] as $item) {
            \App\Models\EntitySource::create([
                'reference_type' => 'App\Models\Item',
                'reference_id' => $item->id,
                'source_id' => $source->id,
                'pages' => '150',
            ]);
        }

        // Extract
        $this->artisan('fixtures:extract', [
            'entity' => 'items',
            '--output' => 'tests/fixtures/test-output',
        ])->assertSuccessful();

        // Verify JSON created
        $path = base_path('tests/fixtures/test-output/entities/items.json');
        $this->assertFileExists($path);

        $data = json_decode(File::get($path), true);
        $this->assertIsArray($data);
        $this->assertGreaterThanOrEqual(9, count($data), 'Should extract at least 9 items');

        // Verify structure
        $item = $data[0];
        $this->assertArrayHasKey('name', $item);
        $this->assertArrayHasKey('slug', $item);
        $this->assertArrayHasKey('item_type', $item);
        $this->assertArrayHasKey('rarity', $item);
        $this->assertArrayHasKey('is_magic', $item);
        $this->assertArrayHasKey('requires_attunement', $item);
        $this->assertArrayHasKey('cost_cp', $item);
        $this->assertArrayHasKey('weight', $item);
        $this->assertArrayHasKey('source', $item);

        // Verify relationships are codes/slugs, not IDs
        $this->assertIsString($item['item_type']);
        $this->assertIsString($item['rarity']);
        $this->assertIsBool($item['is_magic']);

        // Verify we have different rarities
        $rarities = collect($data)->pluck('rarity')->unique();
        $this->assertGreaterThanOrEqual(3, $rarities->count(), 'Should have at least 3 different rarities');

        // Verify we have different item types
        $types = collect($data)->pluck('item_type')->unique();
        $this->assertGreaterThanOrEqual(3, $types->count(), 'Should have at least 3 different item types');

        // Verify we have both magical and mundane items
        $magicalItems = collect($data)->where('is_magic', true);
        $mundaneItems = collect($data)->where('is_magic', false);
        $this->assertGreaterThanOrEqual(1, $magicalItems->count(), 'Should have at least one magical item');
        $this->assertGreaterThanOrEqual(1, $mundaneItems->count(), 'Should have at least one mundane item');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_extracts_feats_with_prerequisite_coverage(): void
    {
        $source = \App\Models\Source::factory()->create(['code' => 'TEST-PHB']);

        // Get ability scores for prerequisites and modifiers
        $strAbility = \App\Models\AbilityScore::where('code', 'STR')->first();
        $dexAbility = \App\Models\AbilityScore::where('code', 'DEX')->first();
        $intAbility = \App\Models\AbilityScore::where('code', 'INT')->first();

        // Create feat without prerequisites
        $grappler = \App\Models\Feat::factory()->create([
            'name' => 'Grappler',
            'slug' => 'grappler',
            'prerequisites_text' => null,
            'description' => 'You have developed the skills necessary to hold your own in close-quarters grappling.',
        ]);

        // Create entity source relationship
        \App\Models\EntitySource::create([
            'reference_type' => 'App\Models\Feat',
            'reference_id' => $grappler->id,
            'source_id' => $source->id,
            'pages' => '167',
        ]);

        // Create feat with prerequisites
        $heavyArmorMaster = \App\Models\Feat::factory()->create([
            'name' => 'Heavy Armor Master',
            'slug' => 'heavy-armor-master',
            'prerequisites_text' => 'Proficiency with heavy armor',
            'description' => 'You can use your armor to deflect strikes that would kill others.',
        ]);

        // Add ability score modifier to this feat
        \App\Models\Modifier::create([
            'reference_type' => 'App\Models\Feat',
            'reference_id' => $heavyArmorMaster->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $strAbility->id,
            'value' => 1,
        ]);

        // Create entity source relationship
        \App\Models\EntitySource::create([
            'reference_type' => 'App\Models\Feat',
            'reference_id' => $heavyArmorMaster->id,
            'source_id' => $source->id,
            'pages' => '167',
        ]);

        // Create feat with prerequisite entity
        $athlete = \App\Models\Feat::factory()->create([
            'name' => 'Athlete',
            'slug' => 'athlete',
            'prerequisites_text' => null,
            'description' => 'You have undergone extensive physical training to gain the following benefits.',
        ]);

        // Add ability score prerequisite
        \App\Models\EntityPrerequisite::create([
            'reference_type' => 'App\Models\Feat',
            'reference_id' => $athlete->id,
            'prerequisite_type' => 'App\Models\AbilityScore',
            'prerequisite_id' => $strAbility->id,
            'minimum_value' => 13,
        ]);

        // Add multiple ability score modifiers (choice)
        \App\Models\Modifier::create([
            'reference_type' => 'App\Models\Feat',
            'reference_id' => $athlete->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $strAbility->id,
            'value' => 1,
            'is_choice' => true,
            'choice_count' => 1,
        ]);

        \App\Models\Modifier::create([
            'reference_type' => 'App\Models\Feat',
            'reference_id' => $athlete->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $dexAbility->id,
            'value' => 1,
            'is_choice' => true,
            'choice_count' => 1,
        ]);

        // Create entity source relationship
        \App\Models\EntitySource::create([
            'reference_type' => 'App\Models\Feat',
            'reference_id' => $athlete->id,
            'source_id' => $source->id,
            'pages' => '165',
        ]);

        // Create feat with ability score improvement only
        $keenMind = \App\Models\Feat::factory()->create([
            'name' => 'Keen Mind',
            'slug' => 'keen-mind',
            'prerequisites_text' => null,
            'description' => 'You have a mind that can track time, direction, and detail with uncanny precision.',
        ]);

        // Add ability score modifier
        \App\Models\Modifier::create([
            'reference_type' => 'App\Models\Feat',
            'reference_id' => $keenMind->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $intAbility->id,
            'value' => 1,
        ]);

        // Create entity source relationship
        \App\Models\EntitySource::create([
            'reference_type' => 'App\Models\Feat',
            'reference_id' => $keenMind->id,
            'source_id' => $source->id,
            'pages' => '167',
        ]);

        // Extract
        $this->artisan('fixtures:extract', [
            'entity' => 'feats',
            '--output' => 'tests/fixtures/test-output',
        ])->assertSuccessful();

        // Verify JSON created
        $path = base_path('tests/fixtures/test-output/entities/feats.json');
        $this->assertFileExists($path);

        $data = json_decode(File::get($path), true);
        $this->assertIsArray($data);
        $this->assertGreaterThanOrEqual(4, count($data), 'Should extract at least 4 feats');

        // Verify structure
        $feat = $data[0];
        $this->assertArrayHasKey('name', $feat);
        $this->assertArrayHasKey('slug', $feat);
        $this->assertArrayHasKey('description', $feat);
        $this->assertArrayHasKey('prerequisites_text', $feat);
        $this->assertArrayHasKey('prerequisites', $feat);
        $this->assertArrayHasKey('ability_score_improvements', $feat);
        $this->assertArrayHasKey('source', $feat);

        // Verify data types
        $this->assertIsString($feat['name']);
        $this->assertIsString($feat['slug']);
        $this->assertIsArray($feat['prerequisites']);
        $this->assertIsArray($feat['ability_score_improvements']);

        // Verify we have both feats with and without prerequisites
        $featsWithPrereqs = collect($data)->filter(fn ($f) => count($f['prerequisites']) > 0);
        $featsWithoutPrereqs = collect($data)->filter(fn ($f) => count($f['prerequisites']) === 0);
        $this->assertGreaterThanOrEqual(1, $featsWithPrereqs->count(), 'Should have at least one feat with prerequisites');
        $this->assertGreaterThanOrEqual(1, $featsWithoutPrereqs->count(), 'Should have at least one feat without prerequisites');

        // Verify we have feats with ability score improvements
        $featsWithASI = collect($data)->filter(fn ($f) => count($f['ability_score_improvements']) > 0);
        $this->assertGreaterThanOrEqual(1, $featsWithASI->count(), 'Should have at least one feat with ability score improvements');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_extracts_backgrounds(): void
    {
        $source = \App\Models\Source::factory()->create(['code' => 'TEST-PHB']);

        // Get skills for proficiencies
        $deception = \App\Models\Skill::where('slug', 'deception')->first();
        $stealth = \App\Models\Skill::where('slug', 'stealth')->first();
        $insight = \App\Models\Skill::where('slug', 'insight')->first();
        $persuasion = \App\Models\Skill::where('slug', 'persuasion')->first();

        // Create background with skill proficiencies
        $criminal = \App\Models\Background::factory()->create([
            'name' => 'Criminal',
            'slug' => 'criminal',
        ]);

        // Add skill proficiencies
        \App\Models\Proficiency::create([
            'reference_type' => 'App\Models\Background',
            'reference_id' => $criminal->id,
            'proficiency_type' => 'skill',
            'skill_id' => $deception->id,
            'proficiency_name' => null,
        ]);

        \App\Models\Proficiency::create([
            'reference_type' => 'App\Models\Background',
            'reference_id' => $criminal->id,
            'proficiency_type' => 'skill',
            'skill_id' => $stealth->id,
            'proficiency_name' => null,
        ]);

        // Add tool proficiency
        \App\Models\Proficiency::create([
            'reference_type' => 'App\Models\Background',
            'reference_id' => $criminal->id,
            'proficiency_type' => 'tool',
            'proficiency_subcategory' => 'gaming_set',
            'proficiency_name' => 'One type of gaming set',
            'is_choice' => true,
            'quantity' => 1,
        ]);

        // Add language proficiency
        \App\Models\Proficiency::create([
            'reference_type' => 'App\Models\Background',
            'reference_id' => $criminal->id,
            'proficiency_type' => 'language',
            'proficiency_name' => 'Two of your choice',
            'is_choice' => true,
            'quantity' => 2,
        ]);

        // Add background feature
        \App\Models\CharacterTrait::create([
            'reference_type' => 'App\Models\Background',
            'reference_id' => $criminal->id,
            'name' => 'Criminal Contact',
            'category' => 'feature',
            'description' => 'You have a reliable and trustworthy contact.',
        ]);

        // Create entity source relationship
        \App\Models\EntitySource::create([
            'reference_type' => 'App\Models\Background',
            'reference_id' => $criminal->id,
            'source_id' => $source->id,
            'pages' => '129',
        ]);

        // Create second background with different proficiencies
        $acolyte = \App\Models\Background::factory()->create([
            'name' => 'Acolyte',
            'slug' => 'acolyte',
        ]);

        // Add different skill proficiencies
        \App\Models\Proficiency::create([
            'reference_type' => 'App\Models\Background',
            'reference_id' => $acolyte->id,
            'proficiency_type' => 'skill',
            'skill_id' => $insight->id,
        ]);

        \App\Models\Proficiency::create([
            'reference_type' => 'App\Models\Background',
            'reference_id' => $acolyte->id,
            'proficiency_type' => 'skill',
            'skill_id' => $persuasion->id,
        ]);

        // Add language proficiency
        \App\Models\Proficiency::create([
            'reference_type' => 'App\Models\Background',
            'reference_id' => $acolyte->id,
            'proficiency_type' => 'language',
            'proficiency_name' => 'Two of your choice',
            'is_choice' => true,
            'quantity' => 2,
        ]);

        // Add background feature
        \App\Models\CharacterTrait::create([
            'reference_type' => 'App\Models\Background',
            'reference_id' => $acolyte->id,
            'name' => 'Shelter of the Faithful',
            'category' => 'feature',
            'description' => 'You command the respect of those who share your faith.',
        ]);

        // Create entity source relationship
        \App\Models\EntitySource::create([
            'reference_type' => 'App\Models\Background',
            'reference_id' => $acolyte->id,
            'source_id' => $source->id,
            'pages' => '127',
        ]);

        // Extract
        $this->artisan('fixtures:extract', [
            'entity' => 'backgrounds',
            '--output' => 'tests/fixtures/test-output',
        ])->assertSuccessful();

        // Verify JSON created
        $path = base_path('tests/fixtures/test-output/entities/backgrounds.json');
        $this->assertFileExists($path);

        $data = json_decode(File::get($path), true);
        $this->assertIsArray($data);
        $this->assertGreaterThanOrEqual(2, count($data), 'Should extract at least 2 backgrounds');

        // Verify structure
        $background = $data[0];
        $this->assertArrayHasKey('name', $background);
        $this->assertArrayHasKey('slug', $background);
        $this->assertArrayHasKey('skill_proficiencies', $background);
        $this->assertArrayHasKey('tool_proficiencies', $background);
        $this->assertArrayHasKey('language_proficiencies', $background);
        $this->assertArrayHasKey('features', $background);
        $this->assertArrayHasKey('source', $background);

        // Verify data types
        $this->assertIsString($background['name']);
        $this->assertIsString($background['slug']);
        $this->assertIsArray($background['skill_proficiencies']);
        $this->assertIsArray($background['tool_proficiencies']);
        $this->assertIsArray($background['language_proficiencies']);
        $this->assertIsArray($background['features']);

        // Verify we have backgrounds with skill proficiencies
        $backgroundsWithSkills = collect($data)->filter(fn ($b) => count($b['skill_proficiencies']) > 0);
        $this->assertGreaterThanOrEqual(1, $backgroundsWithSkills->count(), 'Should have at least one background with skill proficiencies');

        // Verify skill proficiencies are slugs, not IDs
        $backgroundWithSkills = collect($data)->first(fn ($b) => count($b['skill_proficiencies']) > 0);
        if ($backgroundWithSkills) {
            foreach ($backgroundWithSkills['skill_proficiencies'] as $skillProf) {
                $this->assertIsString($skillProf['skill_slug']);
            }
        }

        // Verify features structure
        $backgroundWithFeatures = collect($data)->first(fn ($b) => count($b['features']) > 0);
        if ($backgroundWithFeatures) {
            $feature = $backgroundWithFeatures['features'][0];
            $this->assertArrayHasKey('name', $feature);
            $this->assertArrayHasKey('category', $feature);
            $this->assertArrayHasKey('description', $feature);
        }
    }
}
