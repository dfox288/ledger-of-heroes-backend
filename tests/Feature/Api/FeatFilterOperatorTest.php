<?php

namespace Tests\Feature\Api;

use App\Models\Feat;
use Tests\TestCase;

/**
 * Tests for Feat filter operators using Meilisearch.
 *
 * These tests use pre-imported data from SearchTestExtension.
 * No RefreshDatabase needed - all tests are read-only against shared data.
 */
#[\PHPUnit\Framework\Attributes\Group('feature-search')]
#[\PHPUnit\Framework\Attributes\Group('search-isolated')]
class FeatFilterOperatorTest extends TestCase
{
    protected $seed = false;

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
        $totalFeats = Feat::count();

        $response = $this->getJson("/api/v1/feats?filter=id != {$feat->id}");

        $response->assertOk();
        $this->assertEquals($totalFeats - 1, $response->json('meta.total'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_id_with_greater_than(): void
    {
        // Get a feat ID in the middle range
        $feats = Feat::orderBy('id')->get();
        $middleFeat = $feats->get((int) ($feats->count() / 2));
        $expectedCount = $feats->where('id', '>', $middleFeat->id)->count();

        $response = $this->getJson("/api/v1/feats?filter=id > {$middleFeat->id}");

        $response->assertOk();
        $this->assertEquals($expectedCount, $response->json('meta.total'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_id_with_greater_than_or_equal(): void
    {
        $feats = Feat::orderBy('id')->get();
        $middleFeat = $feats->get((int) ($feats->count() / 2));
        $expectedCount = $feats->where('id', '>=', $middleFeat->id)->count();

        $response = $this->getJson("/api/v1/feats?filter=id >= {$middleFeat->id}");

        $response->assertOk();
        $this->assertEquals($expectedCount, $response->json('meta.total'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_id_with_less_than(): void
    {
        $feats = Feat::orderBy('id')->get();
        $middleFeat = $feats->get((int) ($feats->count() / 2));
        $expectedCount = $feats->where('id', '<', $middleFeat->id)->count();

        $response = $this->getJson("/api/v1/feats?filter=id < {$middleFeat->id}");

        $response->assertOk();
        $this->assertEquals($expectedCount, $response->json('meta.total'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_id_with_less_than_or_equal(): void
    {
        $feats = Feat::orderBy('id')->get();
        $middleFeat = $feats->get((int) ($feats->count() / 2));
        $expectedCount = $feats->where('id', '<=', $middleFeat->id)->count();

        $response = $this->getJson("/api/v1/feats?filter=id <= {$middleFeat->id}");

        $response->assertOk();
        $this->assertEquals($expectedCount, $response->json('meta.total'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_id_with_to_range(): void
    {
        $feats = Feat::orderBy('id')->get();
        $startFeat = $feats->get(2);
        $endFeat = $feats->get(5);
        $expectedCount = $feats->whereBetween('id', [$startFeat->id, $endFeat->id])->count();

        $response = $this->getJson("/api/v1/feats?filter=id {$startFeat->id} TO {$endFeat->id}");

        $response->assertOk();
        $this->assertEquals($expectedCount, $response->json('meta.total'));
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
        $totalFeats = Feat::count();

        $response = $this->getJson('/api/v1/feats?filter=slug != alert');

        $response->assertOk();
        $this->assertEquals($totalFeats - 1, $response->json('meta.total'));
    }

    // ============================================================
    // Boolean Operators (has_prerequisites field) - 4 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_has_prerequisites_with_equals_true(): void
    {
        // PHB feats include feats with prerequisites (e.g., Defensive Duelist requires Dex 13+)
        $featsWithPrereqs = Feat::whereHas('prerequisites')->count();
        $this->assertGreaterThan(0, $featsWithPrereqs, 'PHB should have feats with prerequisites');

        $response = $this->getJson('/api/v1/feats?filter=has_prerequisites = true');

        $response->assertOk();
        $this->assertEquals($featsWithPrereqs, $response->json('meta.total'));

        // Verify all returned feats have prerequisites
        foreach ($response->json('data') as $feat) {
            $featModel = Feat::find($feat['id']);
            $this->assertTrue($featModel->prerequisites()->exists(), "Feat {$feat['name']} should have prerequisites");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_has_prerequisites_with_equals_false(): void
    {
        $featsWithoutPrereqs = Feat::whereDoesntHave('prerequisites')->count();

        $response = $this->getJson('/api/v1/feats?filter=has_prerequisites = false');

        $response->assertOk();
        $this->assertEquals($featsWithoutPrereqs, $response->json('meta.total'));

        // Verify all returned feats do NOT have prerequisites
        foreach ($response->json('data') as $feat) {
            $featModel = Feat::find($feat['id']);
            $this->assertFalse($featModel->prerequisites()->exists(), "Feat {$feat['name']} should not have prerequisites");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_has_prerequisites_with_not_equals_true(): void
    {
        $featsWithoutPrereqs = Feat::whereDoesntHave('prerequisites')->count();

        // != true should return feats without prerequisites
        $response = $this->getJson('/api/v1/feats?filter=has_prerequisites != true');

        $response->assertOk();
        $this->assertEquals($featsWithoutPrereqs, $response->json('meta.total'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_has_prerequisites_with_not_equals_false(): void
    {
        $featsWithPrereqs = Feat::whereHas('prerequisites')->count();

        // != false should return feats with prerequisites
        $response = $this->getJson('/api/v1/feats?filter=has_prerequisites != false');

        $response->assertOk();
        $this->assertEquals($featsWithPrereqs, $response->json('meta.total'));
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
        $totalFeats = Feat::count();
        $this->assertEquals($totalFeats, $response->json('meta.total'), 'All feats returned since none have combat tag');
    }
}
