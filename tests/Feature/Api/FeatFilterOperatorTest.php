<?php

namespace Tests\Feature\Api;

use App\Models\Feat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatFilterOperatorTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear Feat table to avoid interference from seeded data
        \App\Models\Feat::query()->delete();

        // Clear Meilisearch index
        try {
            $client = app(\MeiliSearch\Client::class);
            $indexName = (new \App\Models\Feat)->searchableAs();
            $client->index($indexName)->deleteAllDocuments();
            sleep(1); // Wait for Meilisearch to process deletion
        } catch (\Exception $e) {
            // Ignore if index doesn't exist yet
        }
    }

    // ============================================================
    // Integer Operators (id field) - 7 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_id_with_equals(): void
    {
        // Arrange: Create feats with known IDs
        $feat1 = Feat::factory()->create(['name' => 'Feat 1']);
        $feat2 = Feat::factory()->create(['name' => 'Feat 2']);
        $feat3 = Feat::factory()->create(['name' => 'Feat 3']);

        collect([$feat1, $feat2, $feat3])->searchable();
        sleep(1);

        // Act & Assert
        $response = $this->getJson("/api/v1/feats?filter=id = {$feat2->id}");
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Feat 2');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_id_with_not_equals(): void
    {
        // Arrange
        $feat1 = Feat::factory()->create(['name' => 'Feat 1']);
        $feat2 = Feat::factory()->create(['name' => 'Feat 2']);
        $feat3 = Feat::factory()->create(['name' => 'Feat 3']);

        collect([$feat1, $feat2, $feat3])->searchable();
        sleep(1);

        // Act & Assert
        $response = $this->getJson("/api/v1/feats?filter=id != {$feat2->id}");
        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Feat 1', $names);
        $this->assertContains('Feat 3', $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_id_with_greater_than(): void
    {
        // Arrange
        $feat1 = Feat::factory()->create(['name' => 'Feat 1']);
        $feat2 = Feat::factory()->create(['name' => 'Feat 2']);
        $feat3 = Feat::factory()->create(['name' => 'Feat 3']);
        $feat4 = Feat::factory()->create(['name' => 'Feat 4']);

        collect([$feat1, $feat2, $feat3, $feat4])->searchable();
        sleep(1);

        // Act & Assert
        $response = $this->getJson("/api/v1/feats?filter=id > {$feat2->id}");
        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Feat 3', $names);
        $this->assertContains('Feat 4', $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_id_with_greater_than_or_equal(): void
    {
        // Arrange
        $feat1 = Feat::factory()->create(['name' => 'Feat 1']);
        $feat2 = Feat::factory()->create(['name' => 'Feat 2']);
        $feat3 = Feat::factory()->create(['name' => 'Feat 3']);

        collect([$feat1, $feat2, $feat3])->searchable();
        sleep(1);

        // Act & Assert
        $response = $this->getJson("/api/v1/feats?filter=id >= {$feat2->id}");
        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Feat 2', $names);
        $this->assertContains('Feat 3', $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_id_with_less_than(): void
    {
        // Arrange
        $feat1 = Feat::factory()->create(['name' => 'Feat 1']);
        $feat2 = Feat::factory()->create(['name' => 'Feat 2']);
        $feat3 = Feat::factory()->create(['name' => 'Feat 3']);
        $feat4 = Feat::factory()->create(['name' => 'Feat 4']);

        collect([$feat1, $feat2, $feat3, $feat4])->searchable();
        sleep(1);

        // Act & Assert
        $response = $this->getJson("/api/v1/feats?filter=id < {$feat3->id}");
        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Feat 1', $names);
        $this->assertContains('Feat 2', $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_id_with_less_than_or_equal(): void
    {
        // Arrange
        $feat1 = Feat::factory()->create(['name' => 'Feat 1']);
        $feat2 = Feat::factory()->create(['name' => 'Feat 2']);
        $feat3 = Feat::factory()->create(['name' => 'Feat 3']);

        collect([$feat1, $feat2, $feat3])->searchable();
        sleep(1);

        // Act & Assert
        $response = $this->getJson("/api/v1/feats?filter=id <= {$feat2->id}");
        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Feat 1', $names);
        $this->assertContains('Feat 2', $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_id_with_to_range(): void
    {
        // Arrange
        $feat1 = Feat::factory()->create(['name' => 'Feat 1']);
        $feat2 = Feat::factory()->create(['name' => 'Feat 2']);
        $feat3 = Feat::factory()->create(['name' => 'Feat 3']);
        $feat4 = Feat::factory()->create(['name' => 'Feat 4']);
        $feat5 = Feat::factory()->create(['name' => 'Feat 5']);

        collect([$feat1, $feat2, $feat3, $feat4, $feat5])->searchable();
        sleep(1);

        // Act & Assert
        $response = $this->getJson("/api/v1/feats?filter=id {$feat2->id} TO {$feat4->id}");
        $response->assertOk();
        $response->assertJsonCount(3, 'data');
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Feat 2', $names);
        $this->assertContains('Feat 3', $names);
        $this->assertContains('Feat 4', $names);
    }

    // ============================================================
    // String Operators (slug field) - 2 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_slug_with_equals(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_slug_with_not_equals(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    // ============================================================
    // Boolean Operators (has_prerequisites field) - 4 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_has_prerequisites_with_equals_true(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_has_prerequisites_with_equals_false(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_has_prerequisites_with_not_equals_true(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_has_prerequisites_with_not_equals_false(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    // ============================================================
    // Array Operators (tag_slugs field) - 2 tests
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
}
