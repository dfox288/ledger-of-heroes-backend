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
        // Filter for feats with AbilityScore prerequisites
        $response = $this->getJson('/api/v1/feats?filter=prerequisite_types IN [AbilityScore]&per_page=100');
        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find feats with AbilityScore prerequisites');

        // Verify all returned feats have AbilityScore prerequisite type (check via DB model)
        foreach ($response->json('data') as $feat) {
            $featModel = Feat::find($feat['id']);
            if ($featModel) {
                $hasAbilityScorePrereq = $featModel->prerequisites()
                    ->where('prerequisite_type', 'App\Models\AbilityScore')
                    ->exists();
                $this->assertTrue($hasAbilityScorePrereq, "{$feat['name']} should have AbilityScore prerequisite");
            }
        }

        // Filter for feats with Race prerequisites
        $response = $this->getJson('/api/v1/feats?filter=prerequisite_types IN [Race]&per_page=100');
        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find feats with Race prerequisites');

        // Verify all returned feats have Race prerequisite type (check via DB model)
        foreach ($response->json('data') as $feat) {
            $featModel = Feat::find($feat['id']);
            if ($featModel) {
                $hasRacePrereq = $featModel->prerequisites()
                    ->where('prerequisite_type', 'App\Models\Race')
                    ->exists();
                $this->assertTrue($hasRacePrereq, "{$feat['name']} should have Race prerequisite");
            }
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
        // Filter for feats with STR improvement
        $response = $this->getJson('/api/v1/feats?filter=improved_abilities IN [STR]&per_page=100');
        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find feats that improve STR');

        // Verify all returned feats have STR ability score modifier (check via DB model)
        foreach ($response->json('data') as $feat) {
            $featModel = Feat::find($feat['id']);
            if ($featModel) {
                $hasStrModifier = $featModel->modifiers()
                    ->where('modifier_category', 'ability_score')
                    ->whereHas('abilityScore', fn ($q) => $q->where('code', 'STR'))
                    ->exists();
                $this->assertTrue($hasStrModifier, "{$feat['name']} should have STR ability modifier");
            }
        }

        // Filter for feats with DEX improvement
        $response = $this->getJson('/api/v1/feats?filter=improved_abilities IN [DEX]&per_page=100');
        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find feats that improve DEX');

        // Verify all returned feats have DEX ability score modifier (check via DB model)
        foreach ($response->json('data') as $feat) {
            $featModel = Feat::find($feat['id']);
            if ($featModel) {
                $hasDexModifier = $featModel->modifiers()
                    ->where('modifier_category', 'ability_score')
                    ->whereHas('abilityScore', fn ($q) => $q->where('code', 'DEX'))
                    ->exists();
                $this->assertTrue($hasDexModifier, "{$feat['name']} should have DEX ability modifier");
            }
        }
    }

    #[Test]
    public function it_filters_feats_by_grants_spells()
    {
        // Filter for feats WITH spells
        $response = $this->getJson('/api/v1/feats?filter=grants_spells = true');
        $response->assertOk();

        // Verify all returned feats have spells
        foreach ($response->json('data') as $feat) {
            $featModel = Feat::find($feat['id']);
            $this->assertTrue($featModel->spells()->exists(), "{$feat['name']} should grant spells");
        }

        // Filter for feats WITHOUT spells
        $response = $this->getJson('/api/v1/feats?filter=grants_spells = false');
        $response->assertOk();

        // Verify all returned feats do NOT have spells
        foreach ($response->json('data') as $feat) {
            $featModel = Feat::find($feat['id']);
            $this->assertFalse($featModel->spells()->exists(), "{$feat['name']} should not grant spells");
        }
    }

    #[Test]
    public function it_combines_multiple_filters()
    {
        // Test combined filter returns valid response
        $response = $this->getJson('/api/v1/feats?filter=has_prerequisites = true AND grants_proficiencies = true&per_page=100');
        $response->assertOk();

        // Verify all returned feats have both prerequisites AND proficiencies
        foreach ($response->json('data') as $feat) {
            $featModel = Feat::find($feat['id']);
            if ($featModel) {
                $this->assertTrue($featModel->prerequisites()->exists(), "{$feat['name']} should have prerequisites");
                $this->assertTrue($featModel->proficiencies()->exists(), "{$feat['name']} should grant proficiencies");
            }
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
        // Test pagination with filtered results
        $response = $this->getJson('/api/v1/feats?filter=has_prerequisites = true&per_page=10');
        $response->assertOk();

        // Verify pagination structure
        $this->assertLessThanOrEqual(10, count($response->json('data')));
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find feats with prerequisites');
        $this->assertEquals(10, $response->json('meta.per_page'));

        // Verify all returned feats actually have prerequisites
        foreach ($response->json('data') as $feat) {
            $featModel = Feat::find($feat['id']);
            if ($featModel) {
                $this->assertTrue($featModel->prerequisites()->exists(), "{$feat['name']} should have prerequisites");
            }
        }
    }
}
