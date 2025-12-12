<?php

namespace Tests\Feature\Api;

use App\Models\Feat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for Feat filter operators using Meilisearch.
 *
 * These tests use factory-based data and are self-contained.
 */
#[\PHPUnit\Framework\Attributes\Group('feature-search')]
#[\PHPUnit\Framework\Attributes\Group('search-isolated')]
class FeatFilterOperatorTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = \Database\Seeders\TestDatabaseSeeder::class;

    // ============================================================
    // Integer Operators (id field) - 7 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_id_with_equals(): void
    {
        $feat = Feat::where('name', 'Alert')->first();
        $this->assertNotNull($feat, 'Alert feat should exist from PHB import');

        $response = $this->getJson("/api/v1/feats?filter=id = {$feat->id}");

        $response->assertOk();
        $this->assertEquals(1, $response->json('meta.total'));
        $response->assertJsonPath('data.0.name', 'Alert');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_id_with_not_equals(): void
    {
        $feat = Feat::where('name', 'Alert')->first();
        $this->assertNotNull($feat);

        $response = $this->getJson("/api/v1/feats?filter=id != {$feat->id}");

        $response->assertOk();
        // Verify the filter works - excluded feat should not be in results
        $returnedIds = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertNotContains($feat->id, $returnedIds, 'Alert should not be in results');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_id_with_greater_than(): void
    {
        // Get a feat ID in the middle range
        $feats = Feat::orderBy('id')->get();
        $middleFeat = $feats->get((int) ($feats->count() / 2));

        $response = $this->getJson("/api/v1/feats?filter=id > {$middleFeat->id}");

        $response->assertOk();
        // Verify all returned feats have ID greater than the threshold
        foreach ($response->json('data') as $feat) {
            $this->assertGreaterThan($middleFeat->id, $feat['id']);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_id_with_greater_than_or_equal(): void
    {
        $feats = Feat::orderBy('id')->get();
        $middleFeat = $feats->get((int) ($feats->count() / 2));

        $response = $this->getJson("/api/v1/feats?filter=id >= {$middleFeat->id}");

        $response->assertOk();
        // Verify all returned feats have ID >= threshold
        foreach ($response->json('data') as $feat) {
            $this->assertGreaterThanOrEqual($middleFeat->id, $feat['id']);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_id_with_less_than(): void
    {
        $feats = Feat::orderBy('id')->get();
        $middleFeat = $feats->get((int) ($feats->count() / 2));

        $response = $this->getJson("/api/v1/feats?filter=id < {$middleFeat->id}");

        $response->assertOk();
        // Verify all returned feats have ID < threshold
        foreach ($response->json('data') as $feat) {
            $this->assertLessThan($middleFeat->id, $feat['id']);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_id_with_less_than_or_equal(): void
    {
        $feats = Feat::orderBy('id')->get();
        $middleFeat = $feats->get((int) ($feats->count() / 2));

        $response = $this->getJson("/api/v1/feats?filter=id <= {$middleFeat->id}");

        $response->assertOk();
        // Verify all returned feats have ID <= threshold
        foreach ($response->json('data') as $feat) {
            $this->assertLessThanOrEqual($middleFeat->id, $feat['id']);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_id_with_to_range(): void
    {
        $feats = Feat::orderBy('id')->get();
        $startFeat = $feats->get(2);
        $endFeat = $feats->get(5);

        $response = $this->getJson("/api/v1/feats?filter=id {$startFeat->id} TO {$endFeat->id}");

        $response->assertOk();
        // Verify all returned feats have ID within range
        foreach ($response->json('data') as $feat) {
            $this->assertGreaterThanOrEqual($startFeat->id, $feat['id']);
            $this->assertLessThanOrEqual($endFeat->id, $feat['id']);
        }
    }

    // ============================================================
    // String Operators (slug field) - 2 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_slug_with_equals(): void
    {
        $response = $this->getJson('/api/v1/feats?filter=slug = alert');

        $response->assertOk();
        $this->assertEquals(1, $response->json('meta.total'));
        $response->assertJsonPath('data.0.name', 'Alert');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_slug_with_not_equals(): void
    {
        $response = $this->getJson('/api/v1/feats?filter=slug != alert');

        $response->assertOk();
        // Verify the filter works - 'alert' should not be in results
        $returnedSlugs = collect($response->json('data'))->pluck('slug')->toArray();
        $this->assertNotContains('alert', $returnedSlugs, 'Alert should not be in results');
    }

    // ============================================================
    // Boolean Operators (has_prerequisites field) - 4 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_has_prerequisites_with_equals_true(): void
    {
        $response = $this->getJson('/api/v1/feats?filter=has_prerequisites = true');

        $response->assertOk();

        // PHB has feats with prerequisites - validate all returned results
        foreach ($response->json('data') as $feat) {
            $featModel = Feat::find($feat['id']);
            $this->assertTrue($featModel->prerequisites()->exists(), "Feat {$feat['name']} should have prerequisites");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_has_prerequisites_with_equals_false(): void
    {
        $response = $this->getJson('/api/v1/feats?filter=has_prerequisites = false');

        $response->assertOk();
        // Verify all returned feats do NOT have prerequisites
        foreach ($response->json('data') as $feat) {
            $featModel = Feat::find($feat['id']);
            $this->assertFalse($featModel->prerequisites()->exists(), "Feat {$feat['name']} should not have prerequisites");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_has_prerequisites_with_not_equals_true(): void
    {
        // != true should return feats without prerequisites
        $response = $this->getJson('/api/v1/feats?filter=has_prerequisites != true');

        $response->assertOk();
        // Verify all returned feats do NOT have prerequisites
        foreach ($response->json('data') as $feat) {
            $featModel = Feat::find($feat['id']);
            $this->assertFalse($featModel->prerequisites()->exists(), "Feat {$feat['name']} should not have prerequisites");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_has_prerequisites_with_not_equals_false(): void
    {
        // != false should return feats with prerequisites
        $response = $this->getJson('/api/v1/feats?filter=has_prerequisites != false');

        $response->assertOk();
        // Verify all returned feats have prerequisites
        foreach ($response->json('data') as $feat) {
            $featModel = Feat::find($feat['id']);
            $this->assertTrue($featModel->prerequisites()->exists(), "Feat {$feat['name']} should have prerequisites");
        }
    }

    // ============================================================
    // Array Operators (tag_slugs field) - 2 tests
    // Skipped: No imported feats have tags in the test data
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_tag_slugs_with_in(): void
    {
        // PHB feats don't have tags, so we verify the filter returns empty
        $response = $this->getJson('/api/v1/feats?filter=tag_slugs IN [combat, magic]');

        $response->assertOk();
        $this->assertEquals(0, $response->json('meta.total'), 'No imported feats have tags');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_tag_slugs_with_not_in(): void
    {
        // All feats have no tags, so NOT IN [combat] returns all feats
        $response = $this->getJson('/api/v1/feats?filter=tag_slugs NOT IN [combat]');

        $response->assertOk();

        // Since no imported feats have tags, all should be returned
        // Verify returned feats don't have the combat tag
        foreach ($response->json('data') as $feat) {
            $featModel = Feat::find($feat['id']);
            $tagSlugs = $featModel->tags->pluck('slug')->toArray();
            $this->assertNotContains('combat', $tagSlugs, "Feat {$feat['name']} should not have combat tag");
        }
    }
}
