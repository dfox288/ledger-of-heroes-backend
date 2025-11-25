<?php

namespace Tests\Feature\Api;

use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemFilterOperatorTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear Item table to avoid interference from seeded data
        \App\Models\Item::query()->delete();

        // Clear Meilisearch index
        try {
            $client = app(\MeiliSearch\Client::class);
            $indexName = (new \App\Models\Item)->searchableAs();
            $client->index($indexName)->deleteAllDocuments();
        } catch (\Exception $e) {
            // Ignore if index doesn't exist yet
        }
    }

    // ============================================================
    // Integer Operators (weight field) - 7 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_weight_with_equals(): void
    {
        // Arrange: Create items with different weights
        Item::factory()->create(['name' => 'Feather', 'weight' => 0.5]);
        Item::factory()->create(['name' => 'Dagger', 'weight' => 1]);
        Item::factory()->create(['name' => 'Sword', 'weight' => 5]);
        Item::factory()->create(['name' => 'Shield', 'weight' => 10]);

        Item::latest()->take(4)->get()->searchable();
        sleep(1);

        // Act & Assert
        $response = $this->getJson('/api/v1/items?filter=weight = 5');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Sword');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_weight_with_not_equals(): void
    {
        // Arrange
        Item::factory()->create(['name' => 'Feather', 'weight' => 0.5]);
        Item::factory()->create(['name' => 'Dagger', 'weight' => 1]);
        Item::factory()->create(['name' => 'Sword', 'weight' => 5]);

        Item::latest()->take(3)->get()->searchable();
        sleep(1);

        // Act & Assert
        $response = $this->getJson('/api/v1/items?filter=weight != 1');
        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Feather', $names);
        $this->assertContains('Sword', $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_weight_with_greater_than(): void
    {
        // Arrange
        Item::factory()->create(['name' => 'Feather', 'weight' => 0.5]);
        Item::factory()->create(['name' => 'Dagger', 'weight' => 1]);
        Item::factory()->create(['name' => 'Sword', 'weight' => 5]);
        Item::factory()->create(['name' => 'Armor', 'weight' => 50]);

        Item::latest()->take(4)->get()->searchable();
        sleep(1);

        // Act & Assert
        $response = $this->getJson('/api/v1/items?filter=weight > 5');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Armor');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_weight_with_greater_than_or_equal(): void
    {
        // Arrange
        Item::factory()->create(['name' => 'Feather', 'weight' => 0.5]);
        Item::factory()->create(['name' => 'Sword', 'weight' => 5]);
        Item::factory()->create(['name' => 'Shield', 'weight' => 10]);

        Item::latest()->take(3)->get()->searchable();
        sleep(1);

        // Act & Assert
        $response = $this->getJson('/api/v1/items?filter=weight >= 5');
        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Sword', $names);
        $this->assertContains('Shield', $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_weight_with_less_than(): void
    {
        // Arrange
        Item::factory()->create(['name' => 'Feather', 'weight' => 0.5]);
        Item::factory()->create(['name' => 'Dagger', 'weight' => 1]);
        Item::factory()->create(['name' => 'Sword', 'weight' => 5]);
        Item::factory()->create(['name' => 'Armor', 'weight' => 50]);

        Item::latest()->take(4)->get()->searchable();
        sleep(1);

        // Act & Assert
        $response = $this->getJson('/api/v1/items?filter=weight < 5');
        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Feather', $names);
        $this->assertContains('Dagger', $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_weight_with_less_than_or_equal(): void
    {
        // Arrange
        Item::factory()->create(['name' => 'Feather', 'weight' => 0.5]);
        Item::factory()->create(['name' => 'Dagger', 'weight' => 1]);
        Item::factory()->create(['name' => 'Sword', 'weight' => 5]);

        Item::latest()->take(3)->get()->searchable();
        sleep(1);

        // Act & Assert
        $response = $this->getJson('/api/v1/items?filter=weight <= 1');
        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Feather', $names);
        $this->assertContains('Dagger', $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_weight_with_to_range(): void
    {
        // Arrange
        Item::factory()->create(['name' => 'Feather', 'weight' => 0.5]);
        Item::factory()->create(['name' => 'Dagger', 'weight' => 1]);
        Item::factory()->create(['name' => 'Sword', 'weight' => 5]);
        Item::factory()->create(['name' => 'Armor', 'weight' => 50]);

        Item::latest()->take(4)->get()->searchable();
        sleep(1);

        // Act & Assert
        $response = $this->getJson('/api/v1/items?filter=weight 1 TO 10');
        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Dagger', $names);
        $this->assertContains('Sword', $names);
    }

    // ============================================================
    // String Operators (rarity field) - 2 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_rarity_with_equals(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_rarity_with_not_equals(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    // ============================================================
    // Boolean Operators (requires_attunement field) - 5 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_requires_attunement_with_equals_true(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_requires_attunement_with_equals_false(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_requires_attunement_with_not_equals_true(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_requires_attunement_with_not_equals_false(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_requires_attunement_with_is_null(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    // ============================================================
    // Array Operators (source_codes field) - 3 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_source_codes_with_in(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_source_codes_with_not_in(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_source_codes_with_is_empty(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }
}
