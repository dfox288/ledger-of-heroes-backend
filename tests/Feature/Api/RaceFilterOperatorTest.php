<?php

namespace Tests\Feature\Api;

use App\Models\Race;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RaceFilterOperatorTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear Race table to avoid interference from seeded data
        \App\Models\Race::query()->delete();

        // Clear Meilisearch index
        try {
            $client = app(\MeiliSearch\Client::class);
            $indexName = (new \App\Models\Race)->searchableAs();
            $client->index($indexName)->deleteAllDocuments();
        } catch (\Exception $e) {
            // Ignore if index doesn't exist yet
        }
    }

    // ============================================================
    // Integer Operators (speed field) - 7 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_speed_with_equals(): void
    {
        // Arrange: Create races with different speeds
        Race::factory()->create(['name' => 'Slow Race', 'speed' => 25]);
        Race::factory()->create(['name' => 'Normal Race', 'speed' => 30]);
        Race::factory()->create(['name' => 'Fast Race', 'speed' => 35]);
        Race::factory()->create(['name' => 'Very Fast Race', 'speed' => 40]);

        Race::latest()->take(4)->get()->searchable();
        sleep(1);

        // Act & Assert
        $response = $this->getJson('/api/v1/races?filter=speed = 30');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Normal Race');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_speed_with_not_equals(): void
    {
        // Arrange
        Race::factory()->create(['name' => 'Slow Race', 'speed' => 25]);
        Race::factory()->create(['name' => 'Normal Race', 'speed' => 30]);
        Race::factory()->create(['name' => 'Fast Race', 'speed' => 35]);

        Race::latest()->take(3)->get()->searchable();
        sleep(1);

        // Act & Assert
        $response = $this->getJson('/api/v1/races?filter=speed != 30');
        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Slow Race', $names);
        $this->assertContains('Fast Race', $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_speed_with_greater_than(): void
    {
        // Arrange
        Race::factory()->create(['name' => 'Slow Race', 'speed' => 25]);
        Race::factory()->create(['name' => 'Normal Race', 'speed' => 30]);
        Race::factory()->create(['name' => 'Fast Race', 'speed' => 35]);
        Race::factory()->create(['name' => 'Very Fast Race', 'speed' => 40]);

        Race::latest()->take(4)->get()->searchable();
        sleep(1);

        // Act & Assert
        $response = $this->getJson('/api/v1/races?filter=speed > 30');
        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Fast Race', $names);
        $this->assertContains('Very Fast Race', $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_speed_with_greater_than_or_equal(): void
    {
        // Arrange
        Race::factory()->create(['name' => 'Slow Race', 'speed' => 25]);
        Race::factory()->create(['name' => 'Normal Race', 'speed' => 30]);
        Race::factory()->create(['name' => 'Fast Race', 'speed' => 35]);

        Race::latest()->take(3)->get()->searchable();
        sleep(1);

        // Act & Assert
        $response = $this->getJson('/api/v1/races?filter=speed >= 30');
        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Normal Race', $names);
        $this->assertContains('Fast Race', $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_speed_with_less_than(): void
    {
        // Arrange
        Race::factory()->create(['name' => 'Slow Race', 'speed' => 25]);
        Race::factory()->create(['name' => 'Normal Race', 'speed' => 30]);
        Race::factory()->create(['name' => 'Fast Race', 'speed' => 35]);
        Race::factory()->create(['name' => 'Very Fast Race', 'speed' => 40]);

        Race::latest()->take(4)->get()->searchable();
        sleep(1);

        // Act & Assert
        $response = $this->getJson('/api/v1/races?filter=speed < 35');
        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Slow Race', $names);
        $this->assertContains('Normal Race', $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_speed_with_less_than_or_equal(): void
    {
        // Arrange
        Race::factory()->create(['name' => 'Slow Race', 'speed' => 25]);
        Race::factory()->create(['name' => 'Normal Race', 'speed' => 30]);
        Race::factory()->create(['name' => 'Fast Race', 'speed' => 35]);

        Race::latest()->take(3)->get()->searchable();
        sleep(1);

        // Act & Assert
        $response = $this->getJson('/api/v1/races?filter=speed <= 30');
        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Slow Race', $names);
        $this->assertContains('Normal Race', $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_speed_with_to_range(): void
    {
        // Arrange
        Race::factory()->create(['name' => 'Slow Race', 'speed' => 25]);
        Race::factory()->create(['name' => 'Normal Race', 'speed' => 30]);
        Race::factory()->create(['name' => 'Fast Race', 'speed' => 35]);
        Race::factory()->create(['name' => 'Very Fast Race', 'speed' => 40]);

        Race::latest()->take(4)->get()->searchable();
        sleep(1);

        // Act & Assert
        $response = $this->getJson('/api/v1/races?filter=speed 30 TO 35');
        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Normal Race', $names);
        $this->assertContains('Fast Race', $names);
    }

    // ============================================================
    // String Operators (size_code field) - 2 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_size_code_with_equals(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_size_code_with_not_equals(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    // ============================================================
    // Boolean Operators (is_subrace field) - 4 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_is_subrace_with_equals_true(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_is_subrace_with_equals_false(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_is_subrace_with_not_equals_true(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_is_subrace_with_not_equals_false(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    // ============================================================
    // Array Operators (tag_slugs field) - 3 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_tag_slugs_with_in(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_tag_slugs_with_not_in(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_tag_slugs_with_is_empty(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }
}
