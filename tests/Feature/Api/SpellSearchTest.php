<?php

namespace Tests\Feature\Api;

use App\Models\Spell;
use App\Models\SpellSchool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WaitsForMeilisearch;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-search')]
#[\PHPUnit\Framework\Attributes\Group('search-imported')]
class SpellSearchTest extends TestCase
{
    use RefreshDatabase;
    use WaitsForMeilisearch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('search:configure-indexes');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_searches_spells_using_scout_when_available(): void
    {
        $evocation = SpellSchool::factory()->create(['name' => 'Evocation', 'code' => 'EVO']);

        $fireball = Spell::factory()->create([
            'name' => 'Fireball',
            'spell_school_id' => $evocation->id,
            'description' => 'A bright streak flashes from your pointing finger',
            'level' => 3,
        ]);

        Spell::factory()->create([
            'name' => 'Ice Storm',
            'spell_school_id' => $evocation->id,
            'description' => 'A hail of rock-hard ice pounds',
            'level' => 4,
        ]);

        $this->artisan('scout:import', ['model' => Spell::class]);
        $this->waitForMeilisearchModels(Spell::all()->all());

        $response = $this->getJson('/api/v1/spells?q=fire');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['*' => ['id', 'name', 'description']],
                'meta',
            ])
            ->assertJsonPath('data.0.name', 'Fireball');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_meilisearch_results_by_level_with_search(): void
    {
        $evocation = SpellSchool::factory()->create(['code' => 'EVO']);

        Spell::factory()->create(['name' => 'Fire Bolt', 'level' => 0, 'spell_school_id' => $evocation->id]);
        Spell::factory()->create(['name' => 'Fireball', 'level' => 3, 'spell_school_id' => $evocation->id]);
        Spell::factory()->create(['name' => 'Fire Storm', 'level' => 7, 'spell_school_id' => $evocation->id]);

        $this->artisan('scout:import', ['model' => Spell::class]);
        $this->waitForMeilisearchModels(Spell::all()->all());

        // Phase 1: With search query, now uses Meilisearch with filter syntax
        $response = $this->getJson('/api/v1/spells?q=fire&filter=level = 3');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Fireball');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_empty_search_query_gracefully(): void
    {
        Spell::factory()->count(3)->create();

        // Empty q parameter should return validation error OR be ignored
        // Let's test that omitting 'q' returns all results normally
        $response = $this->getJson('/api/v1/spells');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta'])
            ->assertJsonCount(3, 'data');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_special_characters_in_search(): void
    {
        $spell = Spell::factory()->create([
            'name' => 'Tasha\'s Hideous Laughter',
            'description' => 'A creature falls prone',
        ]);

        $this->artisan('scout:import', ['model' => Spell::class]);
        $this->waitForMeilisearchModels(Spell::all()->all());

        $response = $this->getJson('/api/v1/spells?q='.urlencode("Tasha's"));

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_search_query_minimum_length(): void
    {
        $response = $this->getJson('/api/v1/spells?q=a');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    }
}
