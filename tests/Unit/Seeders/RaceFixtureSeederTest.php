<?php

namespace Tests\Unit\Seeders;

use App\Models\Race;
use Database\Seeders\Testing\RaceFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RaceFixtureSeederTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true; // Seed lookups

    protected function setUp(): void
    {
        parent::setUp();

        // Create minimal test fixture matching the actual format from ExtractFixturesCommand
        $fixturePath = base_path('tests/fixtures/entities/races.json');
        File::ensureDirectoryExists(dirname($fixturePath));
        File::put($fixturePath, json_encode([
            [
                'name' => 'Test Elf',
                'slug' => 'test-elf',
                'size' => 'M', // Size code (Medium)
                'speed' => 30,
                'parent_race_slug' => null,
                'ability_bonuses' => [
                    [
                        'ability' => 'DEX',
                        'bonus' => 2,
                        'is_choice' => false,
                    ],
                ],
                'traits' => [
                    [
                        'name' => 'Darkvision',
                        'category' => 'racial_trait',
                        'description' => 'You can see in dim light within 60 feet.',
                    ],
                ],
                'source' => 'PHB',
                'pages' => '21-23',
            ],
            [
                'name' => 'Test High Elf',
                'slug' => 'test-high-elf',
                'size' => 'M',
                'speed' => 30,
                'parent_race_slug' => 'test-elf', // This is a subrace
                'ability_bonuses' => [
                    [
                        'ability' => 'INT',
                        'bonus' => 1,
                        'is_choice' => false,
                    ],
                ],
                'traits' => [
                    [
                        'name' => 'Cantrip',
                        'category' => 'racial_trait',
                        'description' => 'You know one cantrip of your choice from the wizard spell list.',
                    ],
                ],
                'source' => 'PHB',
                'pages' => '23',
            ],
        ]));
    }

    protected function tearDown(): void
    {
        // Clean up test fixture
        $fixturePath = base_path('tests/fixtures/entities/races.json');
        if (File::exists($fixturePath)) {
            File::delete($fixturePath);
        }

        parent::tearDown();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_races_from_fixture(): void
    {
        $this->assertDatabaseMissing('races', ['slug' => 'test-elf']);

        $seeder = new RaceFixtureSeeder;
        $seeder->run();

        $this->assertDatabaseHas('races', [
            'slug' => 'test-elf',
            'name' => 'Test Elf',
            'speed' => 30,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_resolves_size_by_code(): void
    {
        $seeder = new RaceFixtureSeeder;
        $seeder->run();

        $race = Race::where('slug', 'test-elf')->first();
        $this->assertNotNull($race);
        $this->assertEquals('M', $race->size->code);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_resolves_parent_race_by_slug(): void
    {
        $seeder = new RaceFixtureSeeder;
        $seeder->run();

        // Test that the parent race (test-elf) was created first
        $parentRace = Race::where('slug', 'test-elf')->first();
        $this->assertNotNull($parentRace);
        $this->assertNull($parentRace->parent_race_id);

        // Test that the subrace (test-high-elf) references the parent
        $subrace = Race::where('slug', 'test-high-elf')->first();
        $this->assertNotNull($subrace);
        $this->assertEquals($parentRace->id, $subrace->parent_race_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_ability_bonuses(): void
    {
        $seeder = new RaceFixtureSeeder;
        $seeder->run();

        $race = Race::where('slug', 'test-elf')->first();
        $this->assertNotNull($race);

        // Check that modifier was created for ability score bonus
        $abilityBonuses = $race->modifiers
            ->where('modifier_category', 'ability_score');

        $this->assertCount(1, $abilityBonuses);
        $modifier = $abilityBonuses->first();
        $this->assertEquals('DEX', $modifier->abilityScore->code);
        $this->assertEquals(2, $modifier->value);
        $this->assertFalse($modifier->is_choice);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_traits(): void
    {
        $seeder = new RaceFixtureSeeder;
        $seeder->run();

        $race = Race::where('slug', 'test-elf')->first();
        $this->assertNotNull($race);

        // Check that trait was created
        $this->assertCount(1, $race->traits);
        $trait = $race->traits->first();
        $this->assertEquals('Darkvision', $trait->name);
        $this->assertEquals('racial_trait', $trait->category);
        $this->assertStringContainsString('60 feet', $trait->description);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_entity_sources(): void
    {
        $seeder = new RaceFixtureSeeder;
        $seeder->run();

        $race = Race::where('slug', 'test-elf')->first();
        $this->assertNotNull($race);

        // Check that entity source was created
        $this->assertCount(1, $race->sources);
        $this->assertEquals('PHB', $race->sources->first()->source->code);
        $this->assertEquals('21-23', $race->sources->first()->pages);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_races_with_choice_abilities(): void
    {
        // Add a fixture with ability choice
        $fixturePath = base_path('tests/fixtures/entities/races.json');
        File::put($fixturePath, json_encode([
            [
                'name' => 'Test Half-Elf',
                'slug' => 'test-half-elf',
                'size' => 'M',
                'speed' => 30,
                'parent_race_slug' => null,
                'ability_bonuses' => [
                    [
                        'ability' => 'CHA',
                        'bonus' => 2,
                        'is_choice' => false,
                    ],
                    [
                        'ability' => null, // Choice - player picks
                        'bonus' => 1,
                        'is_choice' => true,
                    ],
                ],
                'traits' => [],
                'source' => 'PHB',
                'pages' => '38-39',
            ],
        ]));

        $seeder = new RaceFixtureSeeder;
        $seeder->run();

        $race = Race::where('slug', 'test-half-elf')->first();
        $this->assertNotNull($race);

        $abilityBonuses = $race->modifiers
            ->where('modifier_category', 'ability_score');

        // Should have 2 ability bonuses
        $this->assertCount(2, $abilityBonuses);

        // First is CHA +2, not a choice
        $chaBonus = $abilityBonuses->firstWhere('is_choice', false);
        $this->assertNotNull($chaBonus);
        $this->assertEquals('CHA', $chaBonus->abilityScore->code);
        $this->assertEquals(2, $chaBonus->value);

        // Second is +1 to player choice
        $choiceBonus = $abilityBonuses->firstWhere('is_choice', true);
        $this->assertNotNull($choiceBonus);
        $this->assertNull($choiceBonus->ability_score_id); // No specific ability
        $this->assertEquals(1, $choiceBonus->value);
    }
}
