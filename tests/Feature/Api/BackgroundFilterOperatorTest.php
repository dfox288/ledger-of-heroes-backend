<?php

namespace Tests\Feature\Api;

use App\Models\Background;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackgroundFilterOperatorTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear Background table to avoid interference from seeded data
        \App\Models\Background::query()->delete();

        // Clear Meilisearch index
        try {
            $client = app(\MeiliSearch\Client::class);
            $indexName = (new \App\Models\Background)->searchableAs();
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
        // Arrange: Create backgrounds with known IDs
        $bg1 = Background::factory()->create(['name' => 'Background 1']);
        $bg2 = Background::factory()->create(['name' => 'Background 2']);
        $bg3 = Background::factory()->create(['name' => 'Background 3']);

        collect([$bg1, $bg2, $bg3])->searchable();
        sleep(1);

        // Act & Assert
        $response = $this->getJson("/api/v1/backgrounds?filter=id = {$bg2->id}");
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Background 2');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_id_with_not_equals(): void
    {
        // Arrange
        $bg1 = Background::factory()->create(['name' => 'Background 1']);
        $bg2 = Background::factory()->create(['name' => 'Background 2']);
        $bg3 = Background::factory()->create(['name' => 'Background 3']);

        collect([$bg1, $bg2, $bg3])->searchable();
        sleep(1);

        // Act & Assert
        $response = $this->getJson("/api/v1/backgrounds?filter=id != {$bg2->id}");
        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Background 1', $names);
        $this->assertContains('Background 3', $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_id_with_greater_than(): void
    {
        // Arrange
        $bg1 = Background::factory()->create(['name' => 'Background 1']);
        $bg2 = Background::factory()->create(['name' => 'Background 2']);
        $bg3 = Background::factory()->create(['name' => 'Background 3']);
        $bg4 = Background::factory()->create(['name' => 'Background 4']);

        collect([$bg1, $bg2, $bg3, $bg4])->searchable();
        sleep(1);

        // Act & Assert
        $response = $this->getJson("/api/v1/backgrounds?filter=id > {$bg2->id}");
        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Background 3', $names);
        $this->assertContains('Background 4', $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_id_with_greater_than_or_equal(): void
    {
        // Arrange
        $bg1 = Background::factory()->create(['name' => 'Background 1']);
        $bg2 = Background::factory()->create(['name' => 'Background 2']);
        $bg3 = Background::factory()->create(['name' => 'Background 3']);

        collect([$bg1, $bg2, $bg3])->searchable();
        sleep(1);

        // Act & Assert
        $response = $this->getJson("/api/v1/backgrounds?filter=id >= {$bg2->id}");
        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Background 2', $names);
        $this->assertContains('Background 3', $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_id_with_less_than(): void
    {
        // Arrange
        $bg1 = Background::factory()->create(['name' => 'Background 1']);
        $bg2 = Background::factory()->create(['name' => 'Background 2']);
        $bg3 = Background::factory()->create(['name' => 'Background 3']);
        $bg4 = Background::factory()->create(['name' => 'Background 4']);

        collect([$bg1, $bg2, $bg3, $bg4])->searchable();
        sleep(1);

        // Act & Assert
        $response = $this->getJson("/api/v1/backgrounds?filter=id < {$bg3->id}");
        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Background 1', $names);
        $this->assertContains('Background 2', $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_id_with_less_than_or_equal(): void
    {
        // Arrange
        $bg1 = Background::factory()->create(['name' => 'Background 1']);
        $bg2 = Background::factory()->create(['name' => 'Background 2']);
        $bg3 = Background::factory()->create(['name' => 'Background 3']);

        collect([$bg1, $bg2, $bg3])->searchable();
        sleep(1);

        // Act & Assert
        $response = $this->getJson("/api/v1/backgrounds?filter=id <= {$bg2->id}");
        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Background 1', $names);
        $this->assertContains('Background 2', $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_id_with_to_range(): void
    {
        // Arrange
        $bg1 = Background::factory()->create(['name' => 'Background 1']);
        $bg2 = Background::factory()->create(['name' => 'Background 2']);
        $bg3 = Background::factory()->create(['name' => 'Background 3']);
        $bg4 = Background::factory()->create(['name' => 'Background 4']);
        $bg5 = Background::factory()->create(['name' => 'Background 5']);

        collect([$bg1, $bg2, $bg3, $bg4, $bg5])->searchable();
        sleep(1);

        // Act & Assert
        $response = $this->getJson("/api/v1/backgrounds?filter=id {$bg2->id} TO {$bg4->id}");
        $response->assertOk();
        $response->assertJsonCount(3, 'data');
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Background 2', $names);
        $this->assertContains('Background 3', $names);
        $this->assertContains('Background 4', $names);
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
    // Boolean Operators (grants_language_choice field) - 4 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_grants_language_choice_with_equals_true(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_grants_language_choice_with_equals_false(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_grants_language_choice_with_not_equals_true(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_grants_language_choice_with_not_equals_false(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    // ============================================================
    // Array Operators (skill_proficiencies field) - 2 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_skill_proficiencies_with_in(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_skill_proficiencies_with_not_in(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }
}
