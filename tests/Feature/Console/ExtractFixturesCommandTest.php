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
}
