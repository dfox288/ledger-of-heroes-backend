<?php

namespace Tests\Feature\Importers;

use App\Models\Monster;
use App\Services\Importers\MonsterImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

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

        $result = $this->importer->import($xmlPath);

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

        $this->importer->import($xmlPath);

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

        $this->importer->import($xmlPath);

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

        $this->importer->import($xmlPath);

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

        $this->importer->import($xmlPath);

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

        $this->importer->import($xmlPath);

        $goblin = Monster::where('slug', 'goblin')->first();
        $reactions = $goblin->actions()->where('action_type', 'reaction')->get();

        $this->assertCount(1, $reactions);
        $this->assertEquals('Parry', $reactions[0]->name);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_imports_legendary_actions(): void
    {
        $xmlPath = base_path('tests/Fixtures/xml/monsters/test-monsters.xml');

        $this->importer->import($xmlPath);

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

        $this->importer->import($xmlPath);

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

        $this->importer->import($xmlPath);

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

        $result = $this->importer->import($xmlPath);

        $this->assertArrayHasKey('DragonStrategy', $result['strategy_stats']);
        $this->assertEquals(1, $result['strategy_stats']['DragonStrategy']['count']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_selects_spellcaster_strategy_for_monsters_with_spells(): void
    {
        $xmlPath = base_path('tests/Fixtures/xml/monsters/test-monsters.xml');

        $result = $this->importer->import($xmlPath);

        $this->assertArrayHasKey('SpellcasterStrategy', $result['strategy_stats']);
        $this->assertEquals(1, $result['strategy_stats']['SpellcasterStrategy']['count']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_selects_default_strategy_for_basic_monsters(): void
    {
        $xmlPath = base_path('tests/Fixtures/xml/monsters/test-monsters.xml');

        $result = $this->importer->import($xmlPath);

        $this->assertArrayHasKey('DefaultStrategy', $result['strategy_stats']);
        $this->assertEquals(1, $result['strategy_stats']['DefaultStrategy']['count']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_updates_existing_monsters_instead_of_creating_duplicates(): void
    {
        $xmlPath = base_path('tests/Fixtures/xml/monsters/test-monsters.xml');

        $this->importer->import($xmlPath);
        $firstCount = Monster::count();

        $this->importer->import($xmlPath);
        $secondCount = Monster::count();

        $this->assertEquals($firstCount, $secondCount);
        $this->assertEquals(3, Monster::count());
    }
}
