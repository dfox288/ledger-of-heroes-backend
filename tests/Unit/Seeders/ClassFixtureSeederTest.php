<?php

namespace Tests\Unit\Seeders;

use App\Models\CharacterClass;
use Database\Seeders\Testing\ClassFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ClassFixtureSeederTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true; // Seed lookups

    protected function setUp(): void
    {
        parent::setUp();

        // Create minimal test fixture matching the actual format from ExtractFixturesCommand
        $fixturePath = base_path('tests/fixtures/entities/classes.json');
        File::ensureDirectoryExists(dirname($fixturePath));
        File::put($fixturePath, json_encode([
            [
                'name' => 'Test Wizard',
                'slug' => 'test-wizard',
                'hit_die' => 6,
                'description' => 'A spellcaster that uses Intelligence.',
                'primary_ability' => 'Intelligence',
                'spellcasting_ability' => 'INT', // AbilityScore code
                'parent_class_slug' => null,
                'proficiencies' => [
                    [
                        'proficiency_type' => 'saving_throw',
                        'proficiency_subcategory' => null,
                        'proficiency_name' => 'Intelligence',
                        'skill_code' => null,
                        'ability_code' => 'INT',
                        'item_slug' => null,
                        'grants' => true,
                        'is_choice' => false,
                        'choice_group' => null,
                        'choice_option' => null,
                        'quantity' => null,
                        'level' => 1,
                    ],
                    [
                        'proficiency_type' => 'saving_throw',
                        'proficiency_subcategory' => null,
                        'proficiency_name' => 'Wisdom',
                        'skill_code' => null,
                        'ability_code' => 'WIS',
                        'item_slug' => null,
                        'grants' => true,
                        'is_choice' => false,
                        'choice_group' => null,
                        'choice_option' => null,
                        'quantity' => null,
                        'level' => 1,
                    ],
                ],
                'source' => 'PHB',
                'pages' => '112-114',
            ],
            [
                'name' => 'Test School of Evocation',
                'slug' => 'test-school-of-evocation',
                'hit_die' => 0,
                'description' => 'A wizard subclass focused on evocation magic.',
                'primary_ability' => null,
                'spellcasting_ability' => null,
                'parent_class_slug' => 'test-wizard', // References parent by slug
                'proficiencies' => [],
                'source' => 'PHB',
                'pages' => '117',
            ],
        ]));
    }

    protected function tearDown(): void
    {
        // Clean up test fixture
        $fixturePath = base_path('tests/fixtures/entities/classes.json');
        if (File::exists($fixturePath)) {
            File::delete($fixturePath);
        }

        parent::tearDown();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_classes_from_fixture(): void
    {
        $this->assertDatabaseMissing('classes', ['slug' => 'test-wizard']);

        $seeder = new ClassFixtureSeeder;
        $seeder->run();

        $this->assertDatabaseHas('classes', [
            'slug' => 'test-wizard',
            'name' => 'Test Wizard',
            'hit_die' => 6,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_resolves_spellcasting_ability_by_code(): void
    {
        $seeder = new ClassFixtureSeeder;
        $seeder->run();

        $class = CharacterClass::where('slug', 'test-wizard')->first();
        $this->assertNotNull($class);
        $this->assertNotNull($class->spellcasting_ability_id);
        $this->assertEquals('INT', $class->spellcastingAbility->code);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_resolves_parent_class_by_slug(): void
    {
        $seeder = new ClassFixtureSeeder;
        $seeder->run();

        $parentClass = CharacterClass::where('slug', 'test-wizard')->first();
        $subclass = CharacterClass::where('slug', 'test-school-of-evocation')->first();

        $this->assertNotNull($parentClass);
        $this->assertNotNull($subclass);
        $this->assertEquals($parentClass->id, $subclass->parent_class_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_proficiencies(): void
    {
        $seeder = new ClassFixtureSeeder;
        $seeder->run();

        $class = CharacterClass::where('slug', 'test-wizard')->first();
        $this->assertNotNull($class);

        // Check that proficiencies were created
        $this->assertCount(2, $class->proficiencies);

        // Check first proficiency (Intelligence saving throw)
        $intProf = $class->proficiencies->where('proficiency_name', 'Intelligence')->first();
        $this->assertNotNull($intProf);
        $this->assertEquals('saving_throw', $intProf->proficiency_type);
        $this->assertEquals('INT', $intProf->abilityScore->code);
        $this->assertTrue($intProf->grants);
        $this->assertFalse($intProf->is_choice);
        $this->assertEquals(1, $intProf->level);

        // Check second proficiency (Wisdom saving throw)
        $wisProf = $class->proficiencies->where('proficiency_name', 'Wisdom')->first();
        $this->assertNotNull($wisProf);
        $this->assertEquals('WIS', $wisProf->abilityScore->code);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_entity_sources(): void
    {
        $seeder = new ClassFixtureSeeder;
        $seeder->run();

        $class = CharacterClass::where('slug', 'test-wizard')->first();
        $this->assertNotNull($class);

        // Check that entity source was created
        $this->assertCount(1, $class->sources);
        $this->assertEquals('PHB', $class->sources->first()->source->code);
        $this->assertEquals('112-114', $class->sources->first()->pages);
    }
}
