<?php

namespace Tests\Feature\Api;

use App\Models\Feat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('search:configure-indexes');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_searches_feats_using_scout_when_available(): void
    {
        Feat::factory()->create([
            'name' => 'Acrobatics',
            'description' => 'Master of acrobatic movement',
        ]);

        Feat::factory()->create([
            'name' => 'Alert',
            'description' => 'Always vigilant and ready for danger',
        ]);

        $this->artisan('scout:import', ['model' => Feat::class]);
        sleep(1);

        $response = $this->getJson('/api/v1/feats?q=acrobatics');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['*' => ['id', 'name', 'description']],
                'meta',
            ])
            ->assertJsonPath('data.0.name', 'Acrobatics');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_search_query_minimum_length(): void
    {
        $response = $this->getJson('/api/v1/feats?q=a');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_empty_search_query_gracefully(): void
    {
        Feat::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/feats');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta'])
            ->assertJsonCount(3, 'data');
    }
}
