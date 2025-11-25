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
        // Arrange
        $feat1 = Feat::factory()->create(['name' => 'Test Feat Alpha', 'slug' => 'test-feat-alpha']);
        $feat2 = Feat::factory()->create(['name' => 'Test Feat Beta', 'slug' => 'test-feat-beta']);
        $feat3 = Feat::factory()->create(['name' => 'Test Feat Gamma', 'slug' => 'test-feat-gamma']);

        collect([$feat1, $feat2, $feat3])->searchable();
        sleep(1);

        // Act & Assert
        $response = $this->getJson('/api/v1/feats?filter=slug = test-feat-beta');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.slug', 'test-feat-beta');
        $response->assertJsonPath('data.0.name', 'Test Feat Beta');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_slug_with_not_equals(): void
    {
        // Arrange
        $feat1 = Feat::factory()->create(['name' => 'Test Feat Alpha', 'slug' => 'test-feat-alpha']);
        $feat2 = Feat::factory()->create(['name' => 'Test Feat Beta', 'slug' => 'test-feat-beta']);
        $feat3 = Feat::factory()->create(['name' => 'Test Feat Gamma', 'slug' => 'test-feat-gamma']);

        collect([$feat1, $feat2, $feat3])->searchable();
        sleep(1);

        // Act & Assert
        $response = $this->getJson('/api/v1/feats?filter=slug != test-feat-beta');
        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $slugs = collect($response->json('data'))->pluck('slug')->toArray();
        $this->assertContains('test-feat-alpha', $slugs);
        $this->assertContains('test-feat-gamma', $slugs);
        $this->assertNotContains('test-feat-beta', $slugs);
    }

    // ============================================================
    // Boolean Operators (has_prerequisites field) - 4 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_has_prerequisites_with_equals_true(): void
    {
        // Arrange: Create feats with and without prerequisites
        $featWithPrereq = Feat::factory()->create(['name' => 'Advanced Feat']);
        $featWithoutPrereq = Feat::factory()->create(['name' => 'Basic Feat']);

        // Add a prerequisite to the first feat
        $race = \App\Models\Race::factory()->create(['name' => 'Elf']);
        \App\Models\EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $featWithPrereq->id,
            'prerequisite_type' => \App\Models\Race::class,
            'prerequisite_id' => $race->id,
        ]);

        collect([$featWithPrereq, $featWithoutPrereq])->searchable();
        sleep(1);

        // Act & Assert
        $response = $this->getJson('/api/v1/feats?filter=has_prerequisites = true');
        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find feats with prerequisites');

        // Verify all returned feats have prerequisites
        foreach ($response->json('data') as $feat) {
            // Check the feat in database to verify has_prerequisites
            $featModel = Feat::find($feat['id']);
            $this->assertTrue($featModel->prerequisites()->exists(), "Feat {$feat['name']} should have prerequisites");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_has_prerequisites_with_equals_false(): void
    {
        // Arrange: Create feats with and without prerequisites
        $featWithPrereq = Feat::factory()->create(['name' => 'Advanced Feat']);
        $featWithoutPrereq = Feat::factory()->create(['name' => 'Basic Feat']);

        // Add a prerequisite to the first feat
        $race = \App\Models\Race::factory()->create(['name' => 'Elf']);
        \App\Models\EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $featWithPrereq->id,
            'prerequisite_type' => \App\Models\Race::class,
            'prerequisite_id' => $race->id,
        ]);

        collect([$featWithPrereq, $featWithoutPrereq])->searchable();
        sleep(1);

        // Act & Assert
        $response = $this->getJson('/api/v1/feats?filter=has_prerequisites = false');
        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find feats without prerequisites');

        // Verify all returned feats do NOT have prerequisites
        foreach ($response->json('data') as $feat) {
            // Check the feat in database to verify no prerequisites
            $featModel = Feat::find($feat['id']);
            $this->assertFalse($featModel->prerequisites()->exists(), "Feat {$feat['name']} should not have prerequisites");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_has_prerequisites_with_not_equals_true(): void
    {
        // Arrange: Create feats with and without prerequisites
        $featWithPrereq = Feat::factory()->create(['name' => 'Advanced Feat']);
        $featWithoutPrereq = Feat::factory()->create(['name' => 'Basic Feat']);

        // Add a prerequisite to the first feat
        $race = \App\Models\Race::factory()->create(['name' => 'Elf']);
        \App\Models\EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $featWithPrereq->id,
            'prerequisite_type' => \App\Models\Race::class,
            'prerequisite_id' => $race->id,
        ]);

        collect([$featWithPrereq, $featWithoutPrereq])->searchable();
        sleep(1);

        // Act & Assert: != true should return false or null (feats without prerequisites)
        $response = $this->getJson('/api/v1/feats?filter=has_prerequisites != true');
        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find feats without prerequisites');

        // Verify all returned feats do NOT have prerequisites
        foreach ($response->json('data') as $feat) {
            $featModel = Feat::find($feat['id']);
            $this->assertFalse($featModel->prerequisites()->exists(), "Feat {$feat['name']} should not have prerequisites (using != true)");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_has_prerequisites_with_not_equals_false(): void
    {
        // Arrange: Create feats with and without prerequisites
        $featWithPrereq = Feat::factory()->create(['name' => 'Advanced Feat']);
        $featWithoutPrereq = Feat::factory()->create(['name' => 'Basic Feat']);

        // Add a prerequisite to the first feat
        $race = \App\Models\Race::factory()->create(['name' => 'Elf']);
        \App\Models\EntityPrerequisite::create([
            'reference_type' => Feat::class,
            'reference_id' => $featWithPrereq->id,
            'prerequisite_type' => \App\Models\Race::class,
            'prerequisite_id' => $race->id,
        ]);

        collect([$featWithPrereq, $featWithoutPrereq])->searchable();
        sleep(1);

        // Act & Assert: != false should return true or null (feats with prerequisites)
        $response = $this->getJson('/api/v1/feats?filter=has_prerequisites != false');
        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find feats with prerequisites');

        // Verify all returned feats DO have prerequisites
        foreach ($response->json('data') as $feat) {
            $featModel = Feat::find($feat['id']);
            $this->assertTrue($featModel->prerequisites()->exists(), "Feat {$feat['name']} should have prerequisites (using != false)");
        }
    }

    // ============================================================
    // Array Operators (tag_slugs field) - 2 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_tag_slugs_with_in(): void
    {
        // Arrange: Create feats with different tags
        $feat1 = Feat::factory()->create(['name' => 'Combat Feat']);
        $feat2 = Feat::factory()->create(['name' => 'Magic Feat']);
        $feat3 = Feat::factory()->create(['name' => 'Skill Feat']);

        // Attach tags
        $feat1->attachTag('combat');
        $feat2->attachTag('magic');
        $feat3->attachTag('skill-improvement');

        collect([$feat1, $feat2, $feat3])->searchable();
        sleep(1);

        // Act & Assert: Filter by tag_slugs IN [combat, magic]
        $response = $this->getJson('/api/v1/feats?filter=tag_slugs IN [combat, magic]');
        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find feats with combat or magic tags');

        // Verify all returned feats have combat OR magic tag
        foreach ($response->json('data') as $feat) {
            $featModel = Feat::find($feat['id']);
            $tagSlugs = $featModel->tags->pluck('slug')->toArray();

            $hasCombatOrMagic = in_array('combat', $tagSlugs) || in_array('magic', $tagSlugs);
            $this->assertTrue($hasCombatOrMagic, "Feat {$feat['name']} should have combat or magic tag");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_tag_slugs_with_not_in(): void
    {
        // Arrange: Create feats with different tags
        $feat1 = Feat::factory()->create(['name' => 'Combat Feat']);
        $feat2 = Feat::factory()->create(['name' => 'Magic Feat']);
        $feat3 = Feat::factory()->create(['name' => 'Skill Feat']);

        // Attach tags
        $feat1->attachTag('combat');
        $feat2->attachTag('magic');
        $feat3->attachTag('skill-improvement');

        collect([$feat1, $feat2, $feat3])->searchable();
        sleep(1);

        // Act & Assert: Filter by tag_slugs NOT IN [combat]
        $response = $this->getJson('/api/v1/feats?filter=tag_slugs NOT IN [combat]');
        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find feats without combat tag');

        // Verify NO returned feats have combat tag
        foreach ($response->json('data') as $feat) {
            $featModel = Feat::find($feat['id']);
            $tagSlugs = $featModel->tags->pluck('slug')->toArray();

            $this->assertNotContains('combat', $tagSlugs, "Feat {$feat['name']} should not have combat tag");
        }
    }
}
