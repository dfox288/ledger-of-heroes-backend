<?php

namespace Tests\Unit\Seeders;

use App\Models\Spell;
use Database\Seeders\Testing\SpellFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SpellFixtureSeederTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true; // Seed lookups

    protected function setUp(): void
    {
        parent::setUp();

        // Create minimal test fixture matching the actual format from ExtractFixturesCommand
        $fixturePath = base_path('tests/fixtures/entities/spells.json');
        File::ensureDirectoryExists(dirname($fixturePath));
        File::put($fixturePath, json_encode([
            [
                'name' => 'Test Fireball',
                'slug' => 'test-fireball',
                'level' => 3,
                'school' => 'EV', // SpellSchool code (Evocation)
                'casting_time' => '1 action',
                'range' => '150 feet',
                'components' => ['V', 'S', 'M'],
                'material_components' => 'bat guano',
                'duration' => 'Instantaneous',
                'needs_concentration' => false,
                'is_ritual' => false,
                'description' => 'A fireball spell.',
                'higher_levels' => null,
                'classes' => [],
                'damage_types' => ['F'], // DamageType code for Fire
                'sources' => [
                    [
                        'code' => 'PHB',
                        'pages' => '241',
                    ],
                ],
            ],
        ]));
    }

    protected function tearDown(): void
    {
        // Clean up test fixture
        $fixturePath = base_path('tests/fixtures/entities/spells.json');
        if (File::exists($fixturePath)) {
            File::delete($fixturePath);
        }

        parent::tearDown();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_spells_from_fixture(): void
    {
        $this->assertDatabaseMissing('spells', ['slug' => 'test-fireball']);

        $seeder = new SpellFixtureSeeder;
        $seeder->run();

        $this->assertDatabaseHas('spells', [
            'slug' => 'test-fireball',
            'name' => 'Test Fireball',
            'level' => 3,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_resolves_school_by_code(): void
    {
        $seeder = new SpellFixtureSeeder;
        $seeder->run();

        $spell = Spell::where('slug', 'test-fireball')->first();
        $this->assertNotNull($spell);
        $this->assertEquals('EV', $spell->spellSchool->code);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_spell_effects_for_damage_types(): void
    {
        $seeder = new SpellFixtureSeeder;
        $seeder->run();

        $spell = Spell::where('slug', 'test-fireball')->first();
        $this->assertNotNull($spell);

        // Check that spell effect was created with correct damage type
        $this->assertCount(1, $spell->effects);
        $this->assertEquals('F', $spell->effects->first()->damageType->code);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_entity_sources(): void
    {
        $seeder = new SpellFixtureSeeder;
        $seeder->run();

        $spell = Spell::where('slug', 'test-fireball')->first();
        $this->assertNotNull($spell);

        // Check that entity source was created
        $this->assertCount(1, $spell->sources);
        $this->assertEquals('PHB', $spell->sources->first()->source->code);
        $this->assertEquals('241', $spell->sources->first()->pages);
    }
}
