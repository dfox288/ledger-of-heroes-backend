<?php

namespace Tests\Feature\Api;

use App\Models\Feat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for Feat filter functionality using Meilisearch.
 *
 * These tests use factory-based data and are self-contained.
 */
#[\PHPUnit\Framework\Attributes\Group('feature-search')]
#[\PHPUnit\Framework\Attributes\Group('search-isolated')]
class FeatFilterTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = \Database\Seeders\TestDatabaseSeeder::class;

    #[Test]
    public function it_filters_feats_by_has_prerequisites()
    {
        // Filter for feats WITH prerequisites
        $response = $this->getJson('/api/v1/feats?filter=has_prerequisites = true');
        $response->assertOk();

        // Verify all returned feats have prerequisites
        foreach ($response->json('data') as $feat) {
            $featModel = Feat::find($feat['id']);
            $this->assertTrue($featModel->prerequisites()->exists(), "{$feat['name']} should have prerequisites");
        }

        // Filter for feats WITHOUT prerequisites
        $response = $this->getJson('/api/v1/feats?filter=has_prerequisites = false');
        $response->assertOk();

        // Verify all returned feats do NOT have prerequisites
        foreach ($response->json('data') as $feat) {
            $featModel = Feat::find($feat['id']);
            $this->assertFalse($featModel->prerequisites()->exists(), "{$feat['name']} should not have prerequisites");
        }
    }

    #[Test]
    public function it_filters_feats_by_prerequisite_type()
    {
        // Get count of feats with AbilityScore prerequisites
        $abilityPrereqCount = Feat::whereHas('prerequisites', function ($q) {
            $q->where('prerequisite_type', 'App\Models\AbilityScore');
        })->count();

        // Get count of feats with Race prerequisites
        $racePrereqCount = Feat::whereHas('prerequisites', function ($q) {
            $q->where('prerequisite_type', 'App\Models\Race');
        })->count();

        if ($abilityPrereqCount === 0 && $racePrereqCount === 0) {
            $this->markTestSkipped('No feats with typed prerequisites in imported data');
        }

        if ($abilityPrereqCount > 0) {
            $response = $this->getJson('/api/v1/feats?filter=prerequisite_types IN [AbilityScore]');
            $response->assertOk();
            $this->assertEquals($abilityPrereqCount, $response->json('meta.total'));
        }

        if ($racePrereqCount > 0) {
            $response = $this->getJson('/api/v1/feats?filter=prerequisite_types IN [Race]');
            $response->assertOk();
            $this->assertEquals($racePrereqCount, $response->json('meta.total'));
        }
    }

    #[Test]
    public function it_filters_feats_by_grants_proficiencies()
    {
        // Filter for feats WITH proficiencies
        $response = $this->getJson('/api/v1/feats?filter=grants_proficiencies = true');
        $response->assertOk();

        // Verify all returned feats have proficiencies
        foreach ($response->json('data') as $feat) {
            $featModel = Feat::find($feat['id']);
            $this->assertTrue($featModel->proficiencies()->exists(), "{$feat['name']} should grant proficiencies");
        }

        // Filter for feats WITHOUT proficiencies
        $response = $this->getJson('/api/v1/feats?filter=grants_proficiencies = false');
        $response->assertOk();

        // Verify all returned feats do NOT have proficiencies
        foreach ($response->json('data') as $feat) {
            $featModel = Feat::find($feat['id']);
            $this->assertFalse($featModel->proficiencies()->exists(), "{$feat['name']} should not grant proficiencies");
        }
    }

    #[Test]
    public function it_filters_feats_by_improved_abilities()
    {
        // Get count of feats with STR improvement
        $strImprovementCount = Feat::whereHas('modifiers', function ($q) {
            $q->where('modifier_category', 'ability_score')
                ->whereHas('abilityScore', fn ($sq) => $sq->where('code', 'STR'));
        })->count();

        // Get count of feats with DEX improvement
        $dexImprovementCount = Feat::whereHas('modifiers', function ($q) {
            $q->where('modifier_category', 'ability_score')
                ->whereHas('abilityScore', fn ($sq) => $sq->where('code', 'DEX'));
        })->count();

        if ($strImprovementCount === 0 && $dexImprovementCount === 0) {
            $this->markTestSkipped('No feats with ability improvements in imported data');
        }

        if ($strImprovementCount > 0) {
            $response = $this->getJson('/api/v1/feats?filter=improved_abilities IN [STR]');
            $response->assertOk();
            $this->assertEquals($strImprovementCount, $response->json('meta.total'));
        }

        if ($dexImprovementCount > 0) {
            $response = $this->getJson('/api/v1/feats?filter=improved_abilities IN [DEX]');
            $response->assertOk();
            $this->assertEquals($dexImprovementCount, $response->json('meta.total'));
        }
    }

    #[Test]
    public function it_combines_multiple_filters()
    {
        // Get count of feats with both prerequisites AND proficiencies
        $combinedCount = Feat::has('prerequisites')
            ->has('proficiencies')
            ->count();

        if ($combinedCount === 0) {
            // Just verify the combined filter returns a valid response
            $response = $this->getJson('/api/v1/feats?filter=has_prerequisites = true AND grants_proficiencies = true');
            $response->assertOk();
            $this->assertEquals(0, $response->json('meta.total'));
        } else {
            $response = $this->getJson('/api/v1/feats?filter=has_prerequisites = true AND grants_proficiencies = true');
            $response->assertOk();
            $this->assertEquals($combinedCount, $response->json('meta.total'));
        }
    }

    #[Test]
    public function it_returns_empty_results_when_no_matches()
    {
        // Test filter that shouldn't match anything
        $response = $this->getJson('/api/v1/feats?filter=prerequisite_types IN [NonExistentType]');
        $response->assertOk();
        $this->assertEquals(0, $response->json('meta.total'));
    }

    #[Test]
    public function it_paginates_filtered_results()
    {
        // Get total feats with prerequisites
        $withPrereqCount = Feat::has('prerequisites')->count();

        if ($withPrereqCount < 2) {
            $this->markTestSkipped('Not enough feats with prerequisites for pagination test');
        }

        $response = $this->getJson('/api/v1/feats?filter=has_prerequisites = true&per_page=10');
        $response->assertOk();

        // Verify pagination structure
        $this->assertLessThanOrEqual(10, count($response->json('data')));
        $this->assertEquals($withPrereqCount, $response->json('meta.total'));
        $this->assertEquals(10, $response->json('meta.per_page'));
    }
}
