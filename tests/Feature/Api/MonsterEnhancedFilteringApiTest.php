<?php

namespace Tests\Feature\Api;

use App\Models\Monster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for Monster Meilisearch filtering features.
 *
 * HISTORICAL NOTE: This file originally contained tests for deprecated custom query
 * parameters (?spells=, ?spell_level=, ?spells_operator=, ?type=, ?min_cr=) that
 * were removed during the API Quality Overhaul (January 25, 2025).
 *
 * Those tests were removed because:
 * 1. The Monster API only supports Meilisearch ?filter= syntax
 * 2. Custom parameters were explicitly removed as "dead code" during cleanup
 * 3. The parameters were never implemented in MonsterSearchService
 *
 * The remaining tests use proper Meilisearch filter syntax and pass successfully.
 *
 * For spell filtering examples using Meilisearch:
 * - Single spell: GET /api/v1/monsters?filter=spell_slugs IN [fireball]
 * - Multiple spells: GET /api/v1/monsters?filter=spell_slugs IN [fireball, lightning-bolt]
 * - Type + spells: GET /api/v1/monsters?filter=type = dragon AND spell_slugs IN [fireball]
 * - CR + spells: GET /api/v1/monsters?filter=challenge_rating >= 10 AND spell_slugs IN [wish]
 *
 * See MonsterController PHPDoc for comprehensive filter examples.
 */
class MonsterEnhancedFilteringApiTest extends TestCase
{
    use RefreshDatabase;

    // ========================================
    // Tag-Based Filtering Tests
    // ========================================

    #[Test]
    public function can_filter_monsters_by_single_tag()
    {
        $source = $this->getSource('MM');

        // Monster with fire_immune tag
        $balor = Monster::factory()->create(['name' => 'Balor']);
        $this->createEntitySource($balor, $source);
        $balor->attachTag('Fire Immune', 'immunity');

        // Monster with poison_immune tag
        $devil = Monster::factory()->create(['name' => 'Devil']);
        $this->createEntitySource($devil, $source);
        $devil->attachTag('Poison Immune', 'immunity');

        // Monster with no tags
        Monster::factory()->create(['name' => 'Goblin']);

        // Index monsters for search
        Monster::where('name', 'Balor')->first()->searchable();
        Monster::where('name', 'Devil')->first()->searchable();
        Monster::where('name', 'Goblin')->first()->searchable();
        sleep(1); // Give Meilisearch time to index

        // Filter by fire-immune tag
        $response = $this->getJson('/api/v1/monsters?filter=tag_slugs IN [fire-immune]');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Balor');

        // Verify tags are included in response
        $tags = $response->json('data.0.tags');
        $this->assertCount(1, $tags);
        $this->assertEquals('Fire Immune', $tags[0]['name']);
        $this->assertEquals('fire-immune', $tags[0]['slug']);
        $this->assertEquals('immunity', $tags[0]['type']);
    }

    #[Test]
    public function can_filter_monsters_by_multiple_tags_or_logic()
    {
        $source = $this->getSource('MM');

        // Fiend with fire immunity
        $balor = Monster::factory()->create(['name' => 'Balor']);
        $this->createEntitySource($balor, $source);
        $balor->attachTag('Fiend', 'creature_type');
        $balor->attachTag('Fire Immune', 'immunity');

        // Fiend without fire immunity
        $devil = Monster::factory()->create(['name' => 'Devil']);
        $this->createEntitySource($devil, $source);
        $devil->attachTag('Fiend', 'creature_type');

        // Fire immune non-fiend
        $salamander = Monster::factory()->create(['name' => 'Salamander']);
        $this->createEntitySource($salamander, $source);
        $salamander->attachTag('Fire Immune', 'immunity');

        // Index monsters for search
        $balor->searchable();
        $devil->searchable();
        $salamander->searchable();
        sleep(1); // Give Meilisearch time to index

        // Filter: fiend OR fire-immune (should get all 3)
        $response = $this->getJson('/api/v1/monsters?filter=tag_slugs IN [fiend, fire-immune]');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Balor', $names);
        $this->assertContains('Devil', $names);
        $this->assertContains('Salamander', $names);
    }

    #[Test]
    public function can_filter_monsters_by_tags_and_challenge_rating()
    {
        $source = $this->getSource('MM');

        // CR 20 fiend (high CR)
        $highCrFiend = Monster::factory()->create(['name' => 'Pit Fiend', 'challenge_rating' => '20']);
        $this->createEntitySource($highCrFiend, $source);
        $highCrFiend->attachTag('Fiend', 'creature_type');

        // CR 2 fiend (low CR)
        $lowCrFiend = Monster::factory()->create(['name' => 'Imp', 'challenge_rating' => '2']);
        $this->createEntitySource($lowCrFiend, $source);
        $lowCrFiend->attachTag('Fiend', 'creature_type');

        // CR 20 non-fiend
        $dragon = Monster::factory()->create(['name' => 'Dragon', 'challenge_rating' => '20']);
        $this->createEntitySource($dragon, $source);
        $dragon->attachTag('Dragon', 'creature_type');

        // Index monsters for search
        $highCrFiend->searchable();
        $lowCrFiend->searchable();
        $dragon->searchable();
        sleep(1); // Give Meilisearch time to index

        // Filter: fiend AND exact CR
        $response = $this->getJson('/api/v1/monsters?filter=tag_slugs IN [fiend] AND challenge_rating = 20');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Pit Fiend');
    }

    #[Test]
    public function tag_filter_returns_empty_when_no_matches()
    {
        $source = $this->getSource('MM');

        // Create monsters without the searched tag
        $goblin = Monster::factory()->create(['name' => 'Goblin']);
        $this->createEntitySource($goblin, $source);
        $goblin->attachTag('Humanoid', 'creature_type');

        // Index monster for search
        $goblin->searchable();
        sleep(1); // Give Meilisearch time to index

        // Search for non-existent tag (using unique tag that won't exist in real data)
        $response = $this->getJson('/api/v1/monsters?filter=tag_slugs IN [test-nonexistent-tag-xyz]');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
        $response->assertJsonPath('meta.total', 0);
    }

    #[Test]
    public function can_combine_tag_filter_with_type_filter()
    {
        $source = $this->getSource('MM');

        // Dragon with fire immunity
        $redDragon = Monster::factory()->create(['name' => 'Red Dragon', 'type' => 'dragon']);
        $this->createEntitySource($redDragon, $source);
        $redDragon->attachTag('Fire Immune', 'immunity');

        // Fiend with fire immunity
        $balor = Monster::factory()->create(['name' => 'Balor', 'type' => 'fiend']);
        $this->createEntitySource($balor, $source);
        $balor->attachTag('Fire Immune', 'immunity');

        // Dragon without fire immunity
        $blueDragon = Monster::factory()->create(['name' => 'Blue Dragon', 'type' => 'dragon']);
        $this->createEntitySource($blueDragon, $source);

        // Index monsters for search
        $redDragon->searchable();
        $balor->searchable();
        $blueDragon->searchable();
        sleep(1); // Give Meilisearch time to index

        // Filter: type=dragon AND fire-immune
        $response = $this->getJson('/api/v1/monsters?filter=type = dragon AND tag_slugs IN [fire-immune]');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Red Dragon');
    }

    /**
     * Get or create a source for testing
     */
    protected function getSource(string $code): \App\Models\Source
    {
        return \App\Models\Source::where('code', $code)->first()
            ?? \App\Models\Source::factory()->create(['code' => $code, 'name' => "Test Source {$code}"]);
    }

    /**
     * Create an entity source relationship
     */
    protected function createEntitySource(Monster $monster, \App\Models\Source $source): void
    {
        \App\Models\EntitySource::create([
            'reference_type' => Monster::class,
            'reference_id' => $monster->id,
            'source_id' => $source->id,
            'pages' => '1',
        ]);
    }
}
