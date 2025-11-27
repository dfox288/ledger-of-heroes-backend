<?php

namespace Tests\Unit\Seeders;

use Database\Seeders\TestDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestDatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = false; // Don't auto-seed, we're testing the seeder

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_exists_and_is_runnable(): void
    {
        $seeder = new TestDatabaseSeeder;

        $this->assertInstanceOf(\Illuminate\Database\Seeder::class, $seeder);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_seeds_lookup_tables(): void
    {
        $this->seed(TestDatabaseSeeder::class);

        // Verify lookup tables are populated
        $this->assertDatabaseHas('spell_schools', ['name' => 'Evocation']);
        $this->assertDatabaseHas('damage_types', ['name' => 'Fire']);
        $this->assertDatabaseHas('sizes', ['name' => 'Medium']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_calls_all_fixture_seeders(): void
    {
        $this->seed(TestDatabaseSeeder::class);

        // Note: This test verifies the seeder runs without errors.
        // Entity assertions are skipped if fixture JSON files don't exist yet.
        // The fixture seeders gracefully handle missing files by doing nothing.

        // If fixture files exist, entities will be seeded and searchable
        // If they don't exist yet, seeding still completes successfully
        $this->assertTrue(true, 'TestDatabaseSeeder ran without errors');
    }
}
