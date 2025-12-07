<?php

namespace Tests\Feature\Importers;

use App\Models\Monster;
use App\Services\Importers\MonsterImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('importers')]
class MonsterImporterTest extends TestCase
{
    use RefreshDatabase;

    private MonsterImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();

        // Create required lookup data
        \App\Models\Size::firstOrCreate(['code' => 'L'], ['name' => 'Large']);
        \App\Models\Size::firstOrCreate(['code' => 'M'], ['name' => 'Medium']);
        \App\Models\Size::firstOrCreate(['code' => 'S'], ['name' => 'Small']);

        $this->importer = new MonsterImporter;
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_imports_monsters_from_xml_file(): void
    {
        $xmlPath = base_path('tests/Fixtures/xml/monsters/test-monsters.xml');

        $result = $this->importer->importWithStats($xmlPath);

        $this->assertArrayHasKey('created', $result);
        $this->assertArrayHasKey('updated', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('strategy_stats', $result);
        $this->assertEquals(3, $result['total']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_dragon_monster_with_correct_attributes(): void
    {
        $xmlPath = base_path('tests/Fixtures/xml/monsters/test-monsters.xml');

        $this->importer->importWithStats($xmlPath);

        $dragon = Monster::where('slug', 'young-red-dragon')->first();

        $this->assertNotNull($dragon);
        $this->assertEquals('Young Red Dragon', $dragon->name);
        $this->assertEquals('dragon', $dragon->type);
        $this->assertEquals('Chaotic Evil', $dragon->alignment);
        $this->assertEquals(18, $dragon->armor_class);
        $this->assertEquals('natural armor', $dragon->armor_type);
        $this->assertEquals(178, $dragon->hit_points_average);
        $this->assertEquals('17d10+85', $dragon->hit_dice);
        $this->assertEquals(40, $dragon->speed_walk);
        $this->assertEquals(80, $dragon->speed_fly);
        $this->assertEquals(40, $dragon->speed_climb);
        $this->assertEquals(23, $dragon->strength);
        $this->assertEquals(10, $dragon->dexterity);
        $this->assertEquals(21, $dragon->constitution);
        $this->assertEquals(14, $dragon->intelligence);
        $this->assertEquals(11, $dragon->wisdom);
        $this->assertEquals(19, $dragon->charisma);
        $this->assertEquals('10', $dragon->challenge_rating);
        $this->assertEquals(5900, $dragon->experience_points);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_spellcaster_monster_with_correct_attributes(): void
    {
        $xmlPath = base_path('tests/Fixtures/xml/monsters/test-monsters.xml');

        $this->importer->importWithStats($xmlPath);

        $acolyte = Monster::where('slug', 'acolyte')->first();

        $this->assertNotNull($acolyte);
        $this->assertEquals('Acolyte', $acolyte->name);
        $this->assertEquals('humanoid (any race)', $acolyte->type);
        $this->assertEquals('Any alignment', $acolyte->alignment);
        $this->assertEquals(10, $acolyte->armor_class);
        $this->assertEquals(9, $acolyte->hit_points_average);
        $this->assertEquals('1/4', $acolyte->challenge_rating);
        $this->assertEquals(50, $acolyte->experience_points);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_imports_monster_traits(): void
    {
        $xmlPath = base_path('tests/Fixtures/xml/monsters/test-monsters.xml');

        $this->importer->importWithStats($xmlPath);

        $dragon = Monster::where('slug', 'young-red-dragon')->first();
        $traits = $dragon->traits;

        $this->assertCount(1, $traits);
        $this->assertEquals('Legendary Resistance (3/Day)', $traits[0]->name);
        $this->assertStringContainsString('saving throw', $traits[0]->description);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_imports_monster_actions(): void
    {
        $xmlPath = base_path('tests/Fixtures/xml/monsters/test-monsters.xml');

        $this->importer->importWithStats($xmlPath);

        $dragon = Monster::where('slug', 'young-red-dragon')->first();
        $actions = $dragon->actions()->where('action_type', 'action')->get();

        $this->assertGreaterThanOrEqual(3, $actions->count());

        $bite = $actions->firstWhere('name', 'Bite');
        $this->assertNotNull($bite);
        $this->assertNotNull($bite->attack_data);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_imports_monster_reactions(): void
    {
        $xmlPath = base_path('tests/Fixtures/xml/monsters/test-monsters.xml');

        $this->importer->importWithStats($xmlPath);

        $goblin = Monster::where('slug', 'goblin')->first();
        $reactions = $goblin->actions()->where('action_type', 'reaction')->get();

        $this->assertCount(1, $reactions);
        $this->assertEquals('Parry', $reactions[0]->name);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_imports_legendary_actions(): void
    {
        $xmlPath = base_path('tests/Fixtures/xml/monsters/test-monsters.xml');

        $this->importer->importWithStats($xmlPath);

        $dragon = Monster::where('slug', 'young-red-dragon')->first();
        $legendary = $dragon->legendaryActions;

        $this->assertCount(2, $legendary);

        $detect = $legendary->firstWhere('name', 'Detect');
        $this->assertEquals(1, $detect->action_cost);

        $wingAttack = $legendary->firstWhere('name', 'Wing Attack (Costs 2 Actions)');
        $this->assertEquals(2, $wingAttack->action_cost);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_imports_monster_modifiers_from_saves_and_skills(): void
    {
        $xmlPath = base_path('tests/Fixtures/xml/monsters/test-monsters.xml');

        $this->importer->importWithStats($xmlPath);

        $dragon = Monster::where('slug', 'young-red-dragon')->first();
        $modifiers = $dragon->modifiers;

        // Should have saves (4) + skills (2)
        $this->assertGreaterThanOrEqual(6, $modifiers->count());

        // Check for a saving throw modifier
        $conSave = $modifiers->where('modifier_category', 'saving_throw_con')->first();
        $this->assertNotNull($conSave);
        $this->assertEquals('9', $conSave->value);

        // Check for a skill modifier
        $perception = $modifiers->where('modifier_category', 'skill_perception')->first();
        $this->assertNotNull($perception);
        $this->assertEquals('8', $perception->value);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_imports_damage_immunities_as_modifiers(): void
    {
        $xmlPath = base_path('tests/Fixtures/xml/monsters/test-monsters.xml');

        $this->importer->importWithStats($xmlPath);

        $dragon = Monster::where('slug', 'young-red-dragon')->first();
        $immunities = $dragon->modifiers()
            ->where('modifier_category', 'damage_immunity')
            ->get();

        $this->assertCount(1, $immunities);
        $this->assertEquals('fire', $immunities[0]->condition);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_selects_dragon_strategy_for_dragon_type(): void
    {
        $xmlPath = base_path('tests/Fixtures/xml/monsters/test-monsters.xml');

        $result = $this->importer->importWithStats($xmlPath);

        $this->assertArrayHasKey('DragonStrategy', $result['strategy_stats']);
        $this->assertEquals(1, $result['strategy_stats']['DragonStrategy']['count']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_selects_spellcaster_strategy_for_monsters_with_spells(): void
    {
        $xmlPath = base_path('tests/Fixtures/xml/monsters/test-monsters.xml');

        $result = $this->importer->importWithStats($xmlPath);

        $this->assertArrayHasKey('SpellcasterStrategy', $result['strategy_stats']);
        $this->assertEquals(1, $result['strategy_stats']['SpellcasterStrategy']['count']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_selects_default_strategy_for_basic_monsters(): void
    {
        $xmlPath = base_path('tests/Fixtures/xml/monsters/test-monsters.xml');

        $result = $this->importer->importWithStats($xmlPath);

        $this->assertArrayHasKey('DefaultStrategy', $result['strategy_stats']);
        $this->assertEquals(1, $result['strategy_stats']['DefaultStrategy']['count']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_updates_existing_monsters_instead_of_creating_duplicates(): void
    {
        $xmlPath = base_path('tests/Fixtures/xml/monsters/test-monsters.xml');

        $this->importer->importWithStats($xmlPath);
        $firstCount = Monster::count();

        $this->importer->importWithStats($xmlPath);
        $secondCount = Monster::count();

        $this->assertEquals($firstCount, $secondCount);
        $this->assertEquals(3, Monster::count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_imports_monster_senses(): void
    {
        // Create required sense types
        \App\Models\Sense::firstOrCreate(['slug' => 'darkvision'], ['name' => 'Darkvision']);
        \App\Models\Sense::firstOrCreate(['slug' => 'blindsight'], ['name' => 'Blindsight']);

        $xmlPath = base_path('tests/Fixtures/xml/monsters/test-monsters.xml');

        $this->importer->importWithStats($xmlPath);

        $dragon = Monster::where('slug', 'young-red-dragon')->first();
        $senses = $dragon->senses()->with('sense')->get();

        // Dragon has: blindsight 30 ft., darkvision 120 ft.
        $this->assertCount(2, $senses);

        $blindsight = $senses->firstWhere('sense.slug', 'blindsight');
        $this->assertNotNull($blindsight);
        $this->assertEquals(30, $blindsight->range_feet);

        $darkvision = $senses->firstWhere('sense.slug', 'darkvision');
        $this->assertNotNull($darkvision);
        $this->assertEquals(120, $darkvision->range_feet);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_clears_existing_senses_on_reimport(): void
    {
        // Create required sense types
        \App\Models\Sense::firstOrCreate(['slug' => 'darkvision'], ['name' => 'Darkvision']);
        \App\Models\Sense::firstOrCreate(['slug' => 'blindsight'], ['name' => 'Blindsight']);

        $xmlPath = base_path('tests/Fixtures/xml/monsters/test-monsters.xml');

        // Import twice
        $this->importer->importWithStats($xmlPath);
        $this->importer->importWithStats($xmlPath);

        $dragon = Monster::where('slug', 'young-red-dragon')->first();
        $senses = $dragon->senses;

        // Should still have exactly 2, not 4 (duplicated)
        $this->assertCount(2, $senses);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_duplicate_senses_in_xml_gracefully(): void
    {
        // Create required sense types
        \App\Models\Sense::firstOrCreate(['slug' => 'darkvision'], ['name' => 'Darkvision']);

        $xmlPath = base_path('tests/Fixtures/xml/monsters/monster-duplicate-senses.xml');

        // This should NOT throw a duplicate key exception
        $result = $this->importer->importWithStats($xmlPath);

        $this->assertEquals(1, $result['total']);

        $monster = Monster::where('slug', 'duplicate-senses-creature')->first();
        $senses = $monster->senses()->with('sense')->get();

        // Should have exactly 1 darkvision, not 2
        $this->assertCount(1, $senses);

        $darkvision = $senses->firstWhere('sense.slug', 'darkvision');
        $this->assertNotNull($darkvision);
        $this->assertEquals(60, $darkvision->range_feet);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_populates_full_slug_when_source_is_present(): void
    {
        // Create a test source
        \App\Models\Source::firstOrCreate(
            ['code' => 'MM'],
            ['name' => 'Monster Manual', 'publication_date' => '2014-09-19']
        );

        // Import monster with source in description
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium>
  <monster>
    <name>Test Goblin</name>
    <size>S</size>
    <type>humanoid</type>
    <alignment>Neutral Evil</alignment>
    <ac>15</ac>
    <hp>7 (2d6)</hp>
    <speed>walk 30 ft.</speed>
    <str>8</str>
    <dex>14</dex>
    <con>10</con>
    <int>10</int>
    <wis>8</wis>
    <cha>8</cha>
    <save></save>
    <skill></skill>
    <passive>9</passive>
    <languages>Common, Goblin</languages>
    <cr>1/4</cr>
    <senses>darkvision 60 ft.</senses>
    <description>A small, green-skinned humanoid.

Source: Monster Manual p. 166</description>
  </monster>
</compendium>
XML;

        $parser = $this->importer->getParser();
        $monsters = $parser->parse($xml);

        $this->importer->import($monsters[0]);

        $goblin = \App\Models\Monster::where('slug', 'test-goblin')->first();

        $this->assertNotNull($goblin);
        $this->assertEquals('mm:test-goblin', $goblin->full_slug);
    }
}
