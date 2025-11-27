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
}
