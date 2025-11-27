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
}
