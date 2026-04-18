<?php

namespace Tests\Feature\Api;

use App\Models\Race;
use Database\Seeders\TestDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\WaitsForMeilisearch;
use Tests\TestCase;

#[Group('feature-search')]
class RaceSearchTest extends TestCase
{
    use RefreshDatabase;
    use WaitsForMeilisearch;

    protected $seeder = TestDatabaseSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('search:configure-indexes');
    }

    #[Test]
    public function it_searches_races_using_scout_when_available(): void
    {
        // Use fixture data - Dwarf and Elf exist in TestDatabaseSeeder
        $response = $this->getJson('/api/v1/races?q=dwarf');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['*' => ['id', 'name', 'slug']],
                'meta',
            ])
            ->assertJsonPath('data.0.name', 'Dwarf');
    }

    #[Test]
    public function it_validates_search_query_minimum_length(): void
    {
        $response = $this->getJson('/api/v1/races?q=a');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    }

    #[Test]
    public function it_handles_empty_search_query_gracefully(): void
    {
        // Use fixture data - returns paginated results (default 15 per page)
        $response = $this->getJson('/api/v1/races');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta'])
            ->assertJsonPath('meta.total', Race::count());
    }

    #[Test]
    public function it_filters_races_by_subrace_required_false(): void
    {
        // Filter for races where subrace selection is optional (base race is complete)
        $response = $this->getJson('/api/v1/races?filter=subrace_required = false AND is_subrace = false');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta']);

        // Human, Dragonborn, Tiefling, Half-Elf, Half-Orc should all have subrace_required: false
        $names = collect($response->json('data'))->pluck('name')->toArray();

        $this->assertContains('Human', $names);
        $this->assertContains('Dragonborn', $names);
        $this->assertContains('Tiefling', $names);
        $this->assertContains('Half-Elf', $names);
        $this->assertContains('Half-Orc', $names);

        // Dwarf should NOT be in the list (requires subrace)
        $this->assertNotContains('Dwarf', $names);
    }

    #[Test]
    public function it_filters_races_by_subrace_required_true(): void
    {
        // Filter for races where subrace selection is required
        $response = $this->getJson('/api/v1/races?filter=subrace_required = true AND is_subrace = false');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta']);

        $names = collect($response->json('data'))->pluck('name')->toArray();

        // Dwarf, Elf, Gnome should require subraces
        $this->assertContains('Dwarf', $names);
        $this->assertContains('Elf', $names);
        $this->assertContains('Gnome', $names);

        // Human should NOT be in the list (optional subrace)
        $this->assertNotContains('Human', $names);
    }
}
