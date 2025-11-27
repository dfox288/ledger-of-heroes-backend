<?php

namespace Tests\Unit\Seeders;

use App\Models\OptionalFeature;
use Database\Seeders\Testing\OptionalFeatureFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class OptionalFeatureFixtureSeederTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true; // Seed lookups

    protected function setUp(): void
    {
        parent::setUp();

        // Create minimal test fixture matching the actual format from ExtractFixturesCommand
        $fixturePath = base_path('tests/fixtures/entities/optionalfeatures.json');
        File::ensureDirectoryExists(dirname($fixturePath));
        File::put($fixturePath, json_encode([
            [
                'name' => 'Test Invocation No Prerequisites',
                'slug' => 'test-invocation-no-prerequisites',
                'feature_type' => 'eldritch_invocation',
                'level_requirement' => null,
                'prerequisite_text' => null,
                'prerequisites' => [],
                'description' => 'A test invocation with no prerequisites.',
                'casting_time' => null,
                'range' => null,
                'duration' => null,
                'spell_school' => null,
                'resource_type' => null,
                'resource_cost' => null,
                'classes' => ['warlock'],
                'subclass_names' => [],
                'source' => 'PHB',
                'pages' => '110',
            ],
            [
                'name' => 'Test Discipline With Level',
                'slug' => 'test-discipline-with-level',
                'feature_type' => 'elemental_discipline',
                'level_requirement' => 11,
                'prerequisite_text' => '11th level Monk',
                'prerequisites' => [
                    [
                        'type' => 'CharacterClass',
                        'value' => 'monk',
                        'minimum_value' => 11,
                    ],
                ],
                'description' => 'A test elemental discipline.',
                'casting_time' => '1 action',
                'range' => '150 feet',
                'duration' => 'Instantaneous',
                'spell_school' => 'EV',
                'resource_type' => 'ki_points',
                'resource_cost' => 4,
                'classes' => ['monk'],
                'subclass_names' => ['Way of the Four Elements'],
                'source' => 'PHB',
                'pages' => '81',
            ],
            [
                'name' => 'Test Fighting Style Multi Class',
                'slug' => 'test-fighting-style-multi-class',
                'feature_type' => 'fighting_style',
                'level_requirement' => null,
                'prerequisite_text' => 'Fighting Style Feature',
                'prerequisites' => [],
                'description' => 'A test fighting style available to multiple classes.',
                'casting_time' => null,
                'range' => null,
                'duration' => null,
                'spell_school' => null,
                'resource_type' => null,
                'resource_cost' => null,
                'classes' => ['fighter', 'paladin', 'ranger'],
                'subclass_names' => [],
                'source' => 'PHB',
                'pages' => '72',
            ],
            [
                'name' => 'Test Maneuver With Subclass',
                'slug' => 'test-maneuver-with-subclass',
                'feature_type' => 'maneuver',
                'level_requirement' => null,
                'prerequisite_text' => null,
                'prerequisites' => [],
                'description' => 'A test maneuver for Battle Master.',
                'casting_time' => null,
                'range' => null,
                'duration' => null,
                'spell_school' => null,
                'resource_type' => null,
                'resource_cost' => null,
                'classes' => ['fighter'],
                'subclass_names' => ['Battle Master'],
                'source' => 'PHB',
                'pages' => '74',
            ],
        ]));
    }

    protected function tearDown(): void
    {
        // Clean up test fixture
        $fixturePath = base_path('tests/fixtures/entities/optionalfeatures.json');
        if (File::exists($fixturePath)) {
            File::delete($fixturePath);
        }

        parent::tearDown();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_optional_features_from_fixture(): void
    {
        $this->assertDatabaseMissing('optional_features', ['slug' => 'test-invocation-no-prerequisites']);

        $seeder = new OptionalFeatureFixtureSeeder;
        $seeder->run();

        $this->assertDatabaseHas('optional_features', [
            'slug' => 'test-invocation-no-prerequisites',
            'name' => 'Test Invocation No Prerequisites',
            'feature_type' => 'eldritch_invocation',
            'level_requirement' => null,
            'prerequisite_text' => null,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_class_associations(): void
    {
        // Create classes that will be referenced
        $warlock = \App\Models\CharacterClass::create([
            'name' => 'Warlock',
            'slug' => 'warlock',
            'hit_die' => 8,
            'description' => 'Test warlock class',
        ]);

        $seeder = new OptionalFeatureFixtureSeeder;
        $seeder->run();

        $feature = OptionalFeature::where('slug', 'test-invocation-no-prerequisites')->first();
        $this->assertNotNull($feature);

        // Check that class association was created
        $this->assertCount(1, $feature->classes);
        $this->assertEquals('warlock', $feature->classes->first()->slug);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_class_associations_with_subclass_name(): void
    {
        // Create classes
        $monk = \App\Models\CharacterClass::create([
            'name' => 'Monk',
            'slug' => 'monk',
            'hit_die' => 8,
            'description' => 'Test monk class',
        ]);

        $seeder = new OptionalFeatureFixtureSeeder;
        $seeder->run();

        $feature = OptionalFeature::where('slug', 'test-discipline-with-level')->first();
        $this->assertNotNull($feature);

        // Check that class association with subclass_name was created
        $this->assertCount(1, $feature->classes);
        $this->assertEquals('monk', $feature->classes->first()->slug);

        // Check the pivot table has subclass_name
        $pivot = $feature->classPivots->first();
        $this->assertNotNull($pivot);
        $this->assertEquals('Way of the Four Elements', $pivot->subclass_name);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_multiple_class_associations(): void
    {
        // Create classes
        $fighter = \App\Models\CharacterClass::create([
            'name' => 'Fighter',
            'slug' => 'fighter',
            'hit_die' => 10,
            'description' => 'Test fighter class',
        ]);

        $paladin = \App\Models\CharacterClass::create([
            'name' => 'Paladin',
            'slug' => 'paladin',
            'hit_die' => 10,
            'description' => 'Test paladin class',
        ]);

        $ranger = \App\Models\CharacterClass::create([
            'name' => 'Ranger',
            'slug' => 'ranger',
            'hit_die' => 10,
            'description' => 'Test ranger class',
        ]);

        $seeder = new OptionalFeatureFixtureSeeder;
        $seeder->run();

        $feature = OptionalFeature::where('slug', 'test-fighting-style-multi-class')->first();
        $this->assertNotNull($feature);

        // Check that all class associations were created
        $this->assertCount(3, $feature->classes);
        $classSlugs = $feature->classes->pluck('slug')->sort()->values();
        $this->assertEquals(['fighter', 'paladin', 'ranger'], $classSlugs->toArray());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_prerequisites(): void
    {
        // Create the class that will be referenced as a prerequisite
        $monk = \App\Models\CharacterClass::create([
            'name' => 'Monk',
            'slug' => 'monk',
            'hit_die' => 8,
            'description' => 'Test monk class',
        ]);

        $seeder = new OptionalFeatureFixtureSeeder;
        $seeder->run();

        $feature = OptionalFeature::where('slug', 'test-discipline-with-level')->first();
        $this->assertNotNull($feature);

        // Check that prerequisite was created
        $prerequisites = $feature->prerequisites;
        $this->assertCount(1, $prerequisites);

        $prereq = $prerequisites->first();
        $this->assertEquals('CharacterClass', class_basename($prereq->prerequisite_type));
        $this->assertEquals('monk', $prereq->prerequisite->slug);
        $this->assertEquals(11, $prereq->minimum_value);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_spell_school_association(): void
    {
        // Create monk class
        $monk = \App\Models\CharacterClass::create([
            'name' => 'Monk',
            'slug' => 'monk',
            'hit_die' => 8,
            'description' => 'Test monk class',
        ]);

        $seeder = new OptionalFeatureFixtureSeeder;
        $seeder->run();

        $feature = OptionalFeature::where('slug', 'test-discipline-with-level')->first();
        $this->assertNotNull($feature);

        // Check that spell school was associated
        $this->assertNotNull($feature->spell_school_id);
        $this->assertEquals('EV', $feature->spellSchool->code);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_entity_sources(): void
    {
        // Create warlock class
        $warlock = \App\Models\CharacterClass::create([
            'name' => 'Warlock',
            'slug' => 'warlock',
            'hit_die' => 8,
            'description' => 'Test warlock class',
        ]);

        $seeder = new OptionalFeatureFixtureSeeder;
        $seeder->run();

        $feature = OptionalFeature::where('slug', 'test-invocation-no-prerequisites')->first();
        $this->assertNotNull($feature);

        // Check that entity source was created
        $this->assertCount(1, $feature->sources);
        $this->assertEquals('PHB', $feature->sources->first()->source->code);
        $this->assertEquals('110', $feature->sources->first()->pages);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_features_without_source(): void
    {
        // Add a feature without source to fixture
        $fixturePath = base_path('tests/fixtures/entities/optionalfeatures.json');
        $data = json_decode(File::get($fixturePath), true);
        $data[] = [
            'name' => 'Test Feature No Source',
            'slug' => 'test-feature-no-source',
            'feature_type' => 'eldritch_invocation',
            'level_requirement' => null,
            'prerequisite_text' => null,
            'prerequisites' => [],
            'description' => 'A test feature with no source.',
            'casting_time' => null,
            'range' => null,
            'duration' => null,
            'spell_school' => null,
            'resource_type' => null,
            'resource_cost' => null,
            'classes' => [],
            'subclass_names' => [],
            'source' => null,
            'pages' => null,
        ];
        File::put($fixturePath, json_encode($data));

        $seeder = new OptionalFeatureFixtureSeeder;
        $seeder->run();

        $feature = OptionalFeature::where('slug', 'test-feature-no-source')->first();
        $this->assertNotNull($feature);
        $this->assertCount(0, $feature->sources);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_features_without_spell_school(): void
    {
        // Create warlock class
        $warlock = \App\Models\CharacterClass::create([
            'name' => 'Warlock',
            'slug' => 'warlock',
            'hit_die' => 8,
            'description' => 'Test warlock class',
        ]);

        $seeder = new OptionalFeatureFixtureSeeder;
        $seeder->run();

        $feature = OptionalFeature::where('slug', 'test-invocation-no-prerequisites')->first();
        $this->assertNotNull($feature);

        // Check that spell school is null
        $this->assertNull($feature->spell_school_id);
    }
}
