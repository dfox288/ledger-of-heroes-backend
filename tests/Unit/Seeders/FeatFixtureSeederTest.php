<?php

namespace Tests\Unit\Seeders;

use App\Models\Feat;
use Database\Seeders\Testing\FeatFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class FeatFixtureSeederTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true; // Seed lookups

    protected function setUp(): void
    {
        parent::setUp();

        // Create minimal test fixture matching the actual format from ExtractFixturesCommand
        $fixturePath = base_path('tests/fixtures/entities/feats.json');
        File::ensureDirectoryExists(dirname($fixturePath));
        File::put($fixturePath, json_encode([
            [
                'name' => 'Test Feat No Prerequisites',
                'slug' => 'test-feat-no-prerequisites',
                'description' => 'A test feat with no prerequisites.',
                'prerequisites_text' => null,
                'prerequisites' => [],
                'ability_score_improvements' => [],
                'source' => 'PHB',
                'pages' => '165',
            ],
            [
                'name' => 'Test Feat With Ability Improvement',
                'slug' => 'test-feat-with-ability-improvement',
                'description' => 'A test feat with ability score improvement.',
                'prerequisites_text' => null,
                'prerequisites' => [],
                'ability_score_improvements' => [
                    [
                        'ability' => 'STR',
                        'value' => 1,
                        'is_choice' => false,
                        'choice_count' => null,
                    ],
                ],
                'source' => 'PHB',
                'pages' => '166',
            ],
            [
                'name' => 'Test Feat With Race Prerequisite',
                'slug' => 'test-feat-with-race-prerequisite',
                'description' => 'A test feat with race prerequisite.',
                'prerequisites_text' => 'Elf',
                'prerequisites' => [
                    [
                        'type' => 'Race',
                        'value' => 'elf',
                    ],
                ],
                'ability_score_improvements' => [
                    [
                        'ability' => 'DEX',
                        'value' => 1,
                        'is_choice' => false,
                        'choice_count' => null,
                    ],
                ],
                'source' => 'PHB',
                'pages' => '167',
            ],
            [
                'name' => 'Test Feat With Ability Prerequisite',
                'slug' => 'test-feat-with-ability-prerequisite',
                'description' => 'A test feat with ability score prerequisite.',
                'prerequisites_text' => 'Strength 13',
                'prerequisites' => [
                    [
                        'type' => 'AbilityScore',
                        'value' => 'STR',
                        'minimum_value' => 13,
                    ],
                ],
                'ability_score_improvements' => [],
                'source' => 'PHB',
                'pages' => '168',
            ],
            [
                'name' => 'Test Feat With Choice',
                'slug' => 'test-feat-with-choice',
                'description' => 'A test feat with ability score choice.',
                'prerequisites_text' => null,
                'prerequisites' => [],
                'ability_score_improvements' => [
                    [
                        'ability' => null,
                        'value' => 1,
                        'is_choice' => true,
                        'choice_count' => 1,
                    ],
                ],
                'source' => 'PHB',
                'pages' => '169',
            ],
        ]));
    }

    protected function tearDown(): void
    {
        // Clean up test fixture
        $fixturePath = base_path('tests/fixtures/entities/feats.json');
        if (File::exists($fixturePath)) {
            File::delete($fixturePath);
        }

        parent::tearDown();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_feats_from_fixture(): void
    {
        $this->assertDatabaseMissing('feats', ['slug' => 'test-feat-no-prerequisites']);

        $seeder = new FeatFixtureSeeder;
        $seeder->run();

        $this->assertDatabaseHas('feats', [
            'slug' => 'test-feat-no-prerequisites',
            'name' => 'Test Feat No Prerequisites',
            'description' => 'A test feat with no prerequisites.',
            'prerequisites_text' => null,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_ability_score_improvements(): void
    {
        $seeder = new FeatFixtureSeeder;
        $seeder->run();

        $feat = Feat::where('slug', 'test-feat-with-ability-improvement')->first();
        $this->assertNotNull($feat);

        // Check that ability score improvement was created
        $modifiers = $feat->modifiers()->where('modifier_category', 'ability_score')->get();
        $this->assertCount(1, $modifiers);

        $modifier = $modifiers->first();
        $this->assertEquals('STR', $modifier->abilityScore->code);
        $this->assertEquals(1, $modifier->value);
        $this->assertFalse($modifier->is_choice);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_race_prerequisites(): void
    {
        // Create the race that will be referenced as a prerequisite
        $elf = \App\Models\Race::create([
            'name' => 'Elf',
            'slug' => 'elf',
            'size_id' => \App\Models\Size::first()->id,
            'speed' => 30,
        ]);

        $seeder = new FeatFixtureSeeder;
        $seeder->run();

        $feat = Feat::where('slug', 'test-feat-with-race-prerequisite')->first();
        $this->assertNotNull($feat);

        // Check that race prerequisite was created
        $prerequisites = $feat->prerequisites;
        $this->assertCount(1, $prerequisites);

        $prereq = $prerequisites->first();
        $this->assertEquals('Race', class_basename($prereq->prerequisite_type));
        $this->assertEquals('elf', $prereq->prerequisite->slug);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_ability_score_prerequisites(): void
    {
        $seeder = new FeatFixtureSeeder;
        $seeder->run();

        $feat = Feat::where('slug', 'test-feat-with-ability-prerequisite')->first();
        $this->assertNotNull($feat);

        // Check that ability score prerequisite was created
        $prerequisites = $feat->prerequisites;
        $this->assertCount(1, $prerequisites);

        $prereq = $prerequisites->first();
        $this->assertEquals('AbilityScore', class_basename($prereq->prerequisite_type));
        $this->assertEquals('STR', $prereq->prerequisite->code);
        $this->assertEquals(13, $prereq->minimum_value);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_ability_score_choice_modifiers(): void
    {
        $seeder = new FeatFixtureSeeder;
        $seeder->run();

        $feat = Feat::where('slug', 'test-feat-with-choice')->first();
        $this->assertNotNull($feat);

        // Check that choice modifier was created
        $modifiers = $feat->modifiers()->where('modifier_category', 'ability_score')->get();
        $this->assertCount(1, $modifiers);

        $modifier = $modifiers->first();
        $this->assertNull($modifier->ability_score_id);
        $this->assertEquals(1, $modifier->value);
        $this->assertTrue($modifier->is_choice);
        $this->assertEquals(1, $modifier->choice_count);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_entity_sources(): void
    {
        $seeder = new FeatFixtureSeeder;
        $seeder->run();

        $feat = Feat::where('slug', 'test-feat-no-prerequisites')->first();
        $this->assertNotNull($feat);

        // Check that entity source was created
        $this->assertCount(1, $feat->sources);
        $this->assertEquals('PHB', $feat->sources->first()->source->code);
        $this->assertEquals('165', $feat->sources->first()->pages);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_feats_without_source(): void
    {
        // Add a feat without source to fixture
        $fixturePath = base_path('tests/fixtures/entities/feats.json');
        $data = json_decode(File::get($fixturePath), true);
        $data[] = [
            'name' => 'Test Feat No Source',
            'slug' => 'test-feat-no-source',
            'description' => 'A test feat with no source.',
            'prerequisites_text' => null,
            'prerequisites' => [],
            'ability_score_improvements' => [],
            'source' => null,
            'pages' => null,
        ];
        File::put($fixturePath, json_encode($data));

        $seeder = new FeatFixtureSeeder;
        $seeder->run();

        $feat = Feat::where('slug', 'test-feat-no-source')->first();
        $this->assertNotNull($feat);
        $this->assertCount(0, $feat->sources);
    }
}
