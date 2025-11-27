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
}
