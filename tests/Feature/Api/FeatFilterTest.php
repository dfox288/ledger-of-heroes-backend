<?php

namespace Tests\Feature\Api;

use App\Models\AbilityScore;
use App\Models\Feat;
use App\Models\Race;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ClearsMeilisearchIndex;
use Tests\Concerns\WaitsForMeilisearch;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-search')]
#[\PHPUnit\Framework\Attributes\Group('search-isolated')]
class FeatFilterTest extends TestCase
{
    use ClearsMeilisearchIndex;
    use RefreshDatabase;
    use WaitsForMeilisearch;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear Meilisearch index for test isolation
        $this->clearMeilisearchIndex(Feat::class);
    }

    #[Test]
    public function it_filters_feats_by_has_prerequisites()
    {
        $strength = AbilityScore::where('code', 'STR')->first();

        // Create feat WITH prerequisites
        $featWithPrereq = Feat::factory()->create(['name' => 'Grappler']);
        $featWithPrereq->prerequisites()->create([
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $strength->id,
            'minimum_value' => 13,
            'group_id' => 1,
        ]);
        $featWithPrereq->fresh()->searchable(); // Re-index for Meilisearch

        // Create feat WITHOUT prerequisites
        $featWithout = Feat::factory()->create(['name' => 'Alert']);
        $featWithout->searchable(); // Re-index for Meilisearch

        $this->waitForMeilisearchModels([$featWithPrereq, $featWithout]);

        // Filter for feats WITHOUT prerequisites
        $response = $this->getJson('/api/v1/feats?filter=has_prerequisites = false');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Alert');

        // Filter for feats WITH prerequisites
        $response = $this->getJson('/api/v1/feats?filter=has_prerequisites = true');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Grappler');
    }

    #[Test]
    public function it_filters_feats_by_prerequisite_type()
    {
        $dwarf = Race::factory()->create(['name' => 'Dwarf', 'slug' => 'dwarf']);
        $strength = AbilityScore::where('code', 'STR')->first();

        // Create feat requiring Race
        $featWithRacePrereq = Feat::factory()->create(['name' => 'Dwarven Fortitude']);
        $featWithRacePrereq->prerequisites()->create([
            'prerequisite_type' => Race::class,
            'prerequisite_id' => $dwarf->id,
            'group_id' => 1,
        ]);
        $featWithRacePrereq->fresh()->searchable();

        // Create feat requiring AbilityScore
        $featWithAbilityPrereq = Feat::factory()->create(['name' => 'Grappler']);
        $featWithAbilityPrereq->prerequisites()->create([
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $strength->id,
            'minimum_value' => 13,
            'group_id' => 1,
        ]);
        $featWithAbilityPrereq->fresh()->searchable();

        // Create feat without prerequisites
        $featWithout = Feat::factory()->create(['name' => 'Alert']);
        $featWithout->searchable();

        $this->waitForMeilisearchModels([$featWithRacePrereq, $featWithAbilityPrereq, $featWithout]);

        // Test filter by Race prerequisite
        $response = $this->getJson('/api/v1/feats?filter=prerequisite_types IN [Race]');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Dwarven Fortitude');

        // Test filter by AbilityScore prerequisite
        $response = $this->getJson('/api/v1/feats?filter=prerequisite_types IN [AbilityScore]');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Grappler');

        // Test filter by either Race OR AbilityScore
        $response = $this->getJson('/api/v1/feats?filter=prerequisite_types IN [Race, AbilityScore]');
        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    #[Test]
    public function it_filters_feats_by_grants_proficiencies()
    {
        // Create feat granting proficiency
        $featGrantingProf = Feat::factory()->create(['name' => 'Weapon Master']);
        $featGrantingProf->proficiencies()->create([
            'proficiency_name' => 'Longsword',
            'proficiency_type' => 'weapon',
        ]);
        $featGrantingProf->fresh()->searchable();

        // Create feat without proficiencies
        $featWithout = Feat::factory()->create(['name' => 'Alert']);
        $featWithout->searchable();

        $this->waitForMeilisearchModels([$featGrantingProf, $featWithout]);

        // Test filter for feats that grant proficiencies
        $response = $this->getJson('/api/v1/feats?filter=grants_proficiencies = true');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Weapon Master');

        // Test filter for feats that DON'T grant proficiencies
        $response = $this->getJson('/api/v1/feats?filter=grants_proficiencies = false');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Alert');
    }

    #[Test]
    public function it_filters_feats_by_improved_abilities()
    {
        $strength = AbilityScore::where('code', 'STR')->first();
        $dexterity = AbilityScore::where('code', 'DEX')->first();

        // Create feat improving STR
        $featImprovingStr = Feat::factory()->create(['name' => 'Athlete (STR)']);
        $featImprovingStr->modifiers()->create([
            'modifier_category' => 'ability_score',
            'ability_score_id' => $strength->id,
            'value' => 1,
        ]);
        $featImprovingStr->searchable();

        // Create feat improving DEX
        $featImprovingDex = Feat::factory()->create(['name' => 'Athlete (DEX)']);
        $featImprovingDex->modifiers()->create([
            'modifier_category' => 'ability_score',
            'ability_score_id' => $dexterity->id,
            'value' => 1,
        ]);
        $featImprovingDex->searchable();

        // Create feat without ASI
        $featWithout = Feat::factory()->create(['name' => 'Alert']);
        $featWithout->searchable();

        $this->waitForMeilisearchModels([$featImprovingStr, $featImprovingDex, $featWithout]);

        // Test filter for STR improvement
        $response = $this->getJson('/api/v1/feats?filter=improved_abilities IN [STR]');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Athlete (STR)');

        // Test filter for DEX improvement
        $response = $this->getJson('/api/v1/feats?filter=improved_abilities IN [DEX]');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Athlete (DEX)');

        // Test filter for STR OR DEX improvement
        $response = $this->getJson('/api/v1/feats?filter=improved_abilities IN [STR, DEX]');
        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    #[Test]
    public function it_combines_multiple_filters()
    {
        $strength = AbilityScore::where('code', 'STR')->first();

        // Feat with STR prerequisite AND grants proficiency
        $grappler = Feat::factory()->create(['name' => 'Grappler']);
        $grappler->prerequisites()->create([
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $strength->id,
            'minimum_value' => 13,
            'group_id' => 1,
        ]);
        $grappler->proficiencies()->create([
            'proficiency_name' => 'Athletics',
            'proficiency_type' => 'skill',
        ]);
        $grappler->fresh()->searchable(); // Refresh to load new relationships

        // Feat with only STR prerequisite
        $heavyArmor = Feat::factory()->create(['name' => 'Heavy Armor Master']);
        $heavyArmor->prerequisites()->create([
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $strength->id,
            'minimum_value' => 13,
            'group_id' => 1,
        ]);
        $heavyArmor->fresh()->searchable(); // Refresh to load new relationships

        // Feat without prerequisites
        $alert = Feat::factory()->create(['name' => 'Alert']);
        $alert->searchable();

        $this->waitForMeilisearchModels([$grappler, $heavyArmor, $alert]);

        // Test combining prerequisite + proficiency filters
        $response = $this->getJson('/api/v1/feats?filter=has_prerequisites = true AND grants_proficiencies = true');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Grappler');

        // Test combining prerequisite type + has prerequisites
        $response = $this->getJson('/api/v1/feats?filter=prerequisite_types IN [AbilityScore] AND has_prerequisites = true');
        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    #[Test]
    public function it_returns_empty_results_when_no_matches()
    {
        $feat = Feat::factory()->create(['name' => 'Alert']);
        $feat->searchable();

        $this->waitForMeilisearch($feat);

        // Test filter that shouldn't match anything
        $response = $this->getJson('/api/v1/feats?filter=prerequisite_types IN [Spell]');
        $response->assertOk();
        $response->assertJsonCount(0, 'data');

        // Test filter for improved abilities that don't exist
        $response = $this->getJson('/api/v1/feats?filter=improved_abilities IN [CHA]');
        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_paginates_filtered_results()
    {
        $strength = AbilityScore::where('code', 'STR')->first();

        // Create 25 feats with STR prerequisite
        for ($i = 1; $i <= 25; $i++) {
            $feat = Feat::factory()->create(['name' => "Feat {$i}"]);
            $feat->prerequisites()->create([
                'prerequisite_type' => AbilityScore::class,
                'prerequisite_id' => $strength->id,
                'minimum_value' => 13,
                'group_id' => 1,
            ]);
            $feat->fresh()->searchable();
        }

        $this->waitForMeilisearchModels(Feat::all()->all());

        $response = $this->getJson('/api/v1/feats?filter=has_prerequisites = true&per_page=10');
        $response->assertOk();
        $response->assertJsonCount(10, 'data');
        $response->assertJsonPath('meta.total', 25);
        $response->assertJsonPath('meta.per_page', 10);
    }
}
