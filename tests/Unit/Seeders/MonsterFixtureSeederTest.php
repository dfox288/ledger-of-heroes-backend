<?php

namespace Tests\Unit\Seeders;

use App\Models\Monster;
use Database\Seeders\Testing\MonsterFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MonsterFixtureSeederTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true; // Seed lookups

    protected function setUp(): void
    {
        parent::setUp();

        // Create minimal test fixture matching the actual format from ExtractFixturesCommand
        $fixturePath = base_path('tests/fixtures/entities/monsters.json');
        File::ensureDirectoryExists(dirname($fixturePath));
        File::put($fixturePath, json_encode([
            [
                'name' => 'Test Dragon',
                'slug' => 'test-dragon',
                'size' => 'L', // Size code (Large)
                'type' => 'dragon',
                'alignment' => 'Chaotic Evil',
                'armor_class' => 18,
                'armor_type' => 'natural armor',
                'hit_points' => 200,
                'hit_dice' => '16d10+112',
                'speed_walk' => 40,
                'speed_fly' => 80,
                'speed_swim' => null,
                'speed_burrow' => null,
                'speed_climb' => null,
                'can_hover' => false,
                'strength' => 23,
                'dexterity' => 10,
                'constitution' => 21,
                'intelligence' => 14,
                'wisdom' => 11,
                'charisma' => 19,
                'challenge_rating' => '13',
                'experience_points' => 10000,
                'passive_perception' => 19,
                'damage_vulnerabilities' => [],
                'damage_resistances' => [],
                'damage_immunities' => ['F'], // DamageType code for Fire
                'condition_immunities' => ['frightened'],
                'description' => 'A test dragon.',
                'is_npc' => false,
                'source' => 'MM',
                'pages' => '86-87',
            ],
        ]));
    }

    protected function tearDown(): void
    {
        // Clean up test fixture
        $fixturePath = base_path('tests/fixtures/entities/monsters.json');
        if (File::exists($fixturePath)) {
            File::delete($fixturePath);
        }

        parent::tearDown();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_monsters_from_fixture(): void
    {
        $this->assertDatabaseMissing('monsters', ['slug' => 'test-dragon']);

        $seeder = new MonsterFixtureSeeder;
        $seeder->run();

        $this->assertDatabaseHas('monsters', [
            'slug' => 'test-dragon',
            'name' => 'Test Dragon',
            'type' => 'dragon',
            'alignment' => 'Chaotic Evil',
            'armor_class' => 18,
            'hit_points_average' => 200,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_resolves_size_by_code(): void
    {
        $seeder = new MonsterFixtureSeeder;
        $seeder->run();

        $monster = Monster::where('slug', 'test-dragon')->first();
        $this->assertNotNull($monster);
        $this->assertEquals('L', $monster->size->code);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_damage_immunities(): void
    {
        $seeder = new MonsterFixtureSeeder;
        $seeder->run();

        $monster = Monster::where('slug', 'test-dragon')->first();
        $this->assertNotNull($monster);

        // Check that modifier was created for damage immunity
        $damageImmunities = $monster->modifiers
            ->where('modifier_category', 'damage_immunity');

        $this->assertCount(1, $damageImmunities);
        $this->assertEquals('F', $damageImmunities->first()->damageType->code);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_condition_immunities(): void
    {
        $seeder = new MonsterFixtureSeeder;
        $seeder->run();

        $monster = Monster::where('slug', 'test-dragon')->first();
        $this->assertNotNull($monster);

        // Check that modifier was created for condition immunity
        $conditionImmunities = $monster->modifiers
            ->where('modifier_category', 'condition_immunity');

        $this->assertCount(1, $conditionImmunities);
        $this->assertEquals('frightened', $conditionImmunities->first()->value);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_damage_resistances(): void
    {
        // Add a fixture with damage resistance
        $fixturePath = base_path('tests/fixtures/entities/monsters.json');
        File::put($fixturePath, json_encode([
            [
                'name' => 'Test Golem',
                'slug' => 'test-golem',
                'size' => 'L',
                'type' => 'construct',
                'alignment' => 'Unaligned',
                'armor_class' => 17,
                'armor_type' => 'natural armor',
                'hit_points' => 178,
                'hit_dice' => '17d10+85',
                'speed_walk' => 30,
                'speed_fly' => null,
                'speed_swim' => null,
                'speed_burrow' => null,
                'speed_climb' => null,
                'can_hover' => false,
                'strength' => 22,
                'dexterity' => 9,
                'constitution' => 20,
                'intelligence' => 3,
                'wisdom' => 11,
                'charisma' => 1,
                'challenge_rating' => '10',
                'experience_points' => 5900,
                'passive_perception' => 10,
                'damage_vulnerabilities' => [],
                'damage_resistances' => ['P', 'S'], // Piercing and Slashing
                'damage_immunities' => [],
                'condition_immunities' => [],
                'description' => 'A test golem.',
                'is_npc' => false,
                'source' => 'MM',
                'pages' => '167',
            ],
        ]));

        $seeder = new MonsterFixtureSeeder;
        $seeder->run();

        $monster = Monster::where('slug', 'test-golem')->first();
        $this->assertNotNull($monster);

        $damageResistances = $monster->modifiers
            ->where('modifier_category', 'damage_resistance');

        $this->assertCount(2, $damageResistances);
        $resistanceCodes = $damageResistances->pluck('damageType.code')->sort()->values();
        $this->assertEquals(['P', 'S'], $resistanceCodes->toArray());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_damage_vulnerabilities(): void
    {
        // Add a fixture with damage vulnerability
        $fixturePath = base_path('tests/fixtures/entities/monsters.json');
        File::put($fixturePath, json_encode([
            [
                'name' => 'Test Twig Blight',
                'slug' => 'test-twig-blight',
                'size' => 'S',
                'type' => 'plant',
                'alignment' => 'Neutral Evil',
                'armor_class' => 13,
                'armor_type' => 'natural armor',
                'hit_points' => 4,
                'hit_dice' => '1d6+1',
                'speed_walk' => 20,
                'speed_fly' => null,
                'speed_swim' => null,
                'speed_burrow' => null,
                'speed_climb' => null,
                'can_hover' => false,
                'strength' => 6,
                'dexterity' => 13,
                'constitution' => 12,
                'intelligence' => 4,
                'wisdom' => 8,
                'charisma' => 3,
                'challenge_rating' => '1/8',
                'experience_points' => 25,
                'passive_perception' => 9,
                'damage_vulnerabilities' => ['F'],
                'damage_resistances' => [],
                'damage_immunities' => [],
                'condition_immunities' => [],
                'description' => 'A test twig blight.',
                'is_npc' => false,
                'source' => 'MM',
                'pages' => '32',
            ],
        ]));

        $seeder = new MonsterFixtureSeeder;
        $seeder->run();

        $monster = Monster::where('slug', 'test-twig-blight')->first();
        $this->assertNotNull($monster);

        $damageVulnerabilities = $monster->modifiers
            ->where('modifier_category', 'damage_vulnerability');

        $this->assertCount(1, $damageVulnerabilities);
        $this->assertEquals('F', $damageVulnerabilities->first()->damageType->code);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_entity_sources(): void
    {
        $seeder = new MonsterFixtureSeeder;
        $seeder->run();

        $monster = Monster::where('slug', 'test-dragon')->first();
        $this->assertNotNull($monster);

        // Check that entity source was created
        $this->assertCount(1, $monster->sources);
        $this->assertEquals('MM', $monster->sources->first()->source->code);
        $this->assertEquals('86-87', $monster->sources->first()->pages);
    }
}
