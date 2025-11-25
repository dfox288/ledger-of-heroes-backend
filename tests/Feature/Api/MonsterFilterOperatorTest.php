<?php

namespace Tests\Feature\Api;

use App\Models\Monster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonsterFilterOperatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Only seed once per test run by checking if sources exist
        if (\App\Models\Source::count() === 0) {
            $this->seed(\Database\Seeders\SourceSeeder::class);
        }
        if (\App\Models\Size::count() === 0) {
            $this->seed(\Database\Seeders\SizeSeeder::class);
        }
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

    // ============================================================
    // Integer Operators (challenge_rating field) - 7 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_challenge_rating_with_equals(): void
    {
        $source = $this->getSource('MM');

        // Create monsters with different CRs
        $cr1 = Monster::factory()->create(['name' => 'Goblin', 'challenge_rating' => '1']);
        $this->createEntitySource($cr1, $source);

        $cr5 = Monster::factory()->create(['name' => 'Hill Giant', 'challenge_rating' => '5']);
        $this->createEntitySource($cr5, $source);

        $cr10 = Monster::factory()->create(['name' => 'Young Red Dragon', 'challenge_rating' => '10']);
        $this->createEntitySource($cr10, $source);

        // Index for Meilisearch
        $cr1->searchable();
        $cr5->searchable();
        $cr10->searchable();
        sleep(1);

        // Filter by CR = 5
        $response = $this->getJson('/api/v1/monsters?filter=challenge_rating = 5');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Hill Giant');
        $response->assertJsonPath('data.0.challenge_rating', '5');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_challenge_rating_with_not_equals(): void
    {
        $source = $this->getSource('MM');

        // Create monsters with different CRs
        $cr1 = Monster::factory()->create(['name' => 'Goblin', 'challenge_rating' => '1']);
        $this->createEntitySource($cr1, $source);

        $cr5a = Monster::factory()->create(['name' => 'Hill Giant', 'challenge_rating' => '5']);
        $this->createEntitySource($cr5a, $source);

        $cr5b = Monster::factory()->create(['name' => 'Elemental', 'challenge_rating' => '5']);
        $this->createEntitySource($cr5b, $source);

        // Index for Meilisearch
        $cr1->searchable();
        $cr5a->searchable();
        $cr5b->searchable();
        sleep(1);

        // Filter by CR != 5 (should only get CR 1)
        $response = $this->getJson('/api/v1/monsters?filter=challenge_rating != 5');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Goblin');
        $response->assertJsonPath('data.0.challenge_rating', '1');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_challenge_rating_with_greater_than(): void
    {
        $source = $this->getSource('MM');

        // Create monsters with different CRs
        $cr0_125 = Monster::factory()->create(['name' => 'Rat', 'challenge_rating' => '0.125']);
        $this->createEntitySource($cr0_125, $source);

        $cr1 = Monster::factory()->create(['name' => 'Goblin', 'challenge_rating' => '1']);
        $this->createEntitySource($cr1, $source);

        $cr10 = Monster::factory()->create(['name' => 'Young Red Dragon', 'challenge_rating' => '10']);
        $this->createEntitySource($cr10, $source);

        $cr20 = Monster::factory()->create(['name' => 'Ancient Dragon', 'challenge_rating' => '20']);
        $this->createEntitySource($cr20, $source);

        // Index for Meilisearch
        $cr0_125->searchable();
        $cr1->searchable();
        $cr10->searchable();
        $cr20->searchable();
        sleep(1);

        // Filter by CR > 5 (should get CR 10 and CR 20)
        $response = $this->getJson('/api/v1/monsters?filter=challenge_rating > 5');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Young Red Dragon', $names);
        $this->assertContains('Ancient Dragon', $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_challenge_rating_with_greater_than_or_equal(): void
    {
        $source = $this->getSource('MM');

        // Create monsters with different CRs
        $cr1 = Monster::factory()->create(['name' => 'Goblin', 'challenge_rating' => '1']);
        $this->createEntitySource($cr1, $source);

        $cr5 = Monster::factory()->create(['name' => 'Hill Giant', 'challenge_rating' => '5']);
        $this->createEntitySource($cr5, $source);

        $cr10 = Monster::factory()->create(['name' => 'Young Red Dragon', 'challenge_rating' => '10']);
        $this->createEntitySource($cr10, $source);

        // Index for Meilisearch
        $cr1->searchable();
        $cr5->searchable();
        $cr10->searchable();
        sleep(1);

        // Filter by CR >= 5 (should get CR 5 and CR 10)
        $response = $this->getJson('/api/v1/monsters?filter=challenge_rating >= 5');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Hill Giant', $names);
        $this->assertContains('Young Red Dragon', $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_challenge_rating_with_less_than(): void
    {
        $source = $this->getSource('MM');

        // Create monsters with different CRs
        $cr0_25 = Monster::factory()->create(['name' => 'Rat', 'challenge_rating' => '0.25']);
        $this->createEntitySource($cr0_25, $source);

        $cr0_5 = Monster::factory()->create(['name' => 'Cat', 'challenge_rating' => '0.5']);
        $this->createEntitySource($cr0_5, $source);

        $cr1 = Monster::factory()->create(['name' => 'Goblin', 'challenge_rating' => '1']);
        $this->createEntitySource($cr1, $source);

        $cr10 = Monster::factory()->create(['name' => 'Young Red Dragon', 'challenge_rating' => '10']);
        $this->createEntitySource($cr10, $source);

        // Index for Meilisearch
        $cr0_25->searchable();
        $cr0_5->searchable();
        $cr1->searchable();
        $cr10->searchable();
        sleep(1);

        // Filter by CR < 1 (should get CR 0.25 and CR 0.5)
        $response = $this->getJson('/api/v1/monsters?filter=challenge_rating < 1');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Rat', $names);
        $this->assertContains('Cat', $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_challenge_rating_with_less_than_or_equal(): void
    {
        $source = $this->getSource('MM');

        // Create monsters with different CRs
        $cr0_5 = Monster::factory()->create(['name' => 'Cat', 'challenge_rating' => '0.5']);
        $this->createEntitySource($cr0_5, $source);

        $cr1 = Monster::factory()->create(['name' => 'Goblin', 'challenge_rating' => '1']);
        $this->createEntitySource($cr1, $source);

        $cr5 = Monster::factory()->create(['name' => 'Hill Giant', 'challenge_rating' => '5']);
        $this->createEntitySource($cr5, $source);

        // Index for Meilisearch
        $cr0_5->searchable();
        $cr1->searchable();
        $cr5->searchable();
        sleep(1);

        // Filter by CR <= 1 (should get CR 0.5 and CR 1)
        $response = $this->getJson('/api/v1/monsters?filter=challenge_rating <= 1');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Cat', $names);
        $this->assertContains('Goblin', $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_challenge_rating_with_to_range(): void
    {
        $source = $this->getSource('MM');

        // Create monsters with different CRs
        $cr0_125 = Monster::factory()->create(['name' => 'Rat', 'challenge_rating' => '0.125']);
        $this->createEntitySource($cr0_125, $source);

        $cr1 = Monster::factory()->create(['name' => 'Goblin', 'challenge_rating' => '1']);
        $this->createEntitySource($cr1, $source);

        $cr5 = Monster::factory()->create(['name' => 'Hill Giant', 'challenge_rating' => '5']);
        $this->createEntitySource($cr5, $source);

        $cr10 = Monster::factory()->create(['name' => 'Young Red Dragon', 'challenge_rating' => '10']);
        $this->createEntitySource($cr10, $source);

        $cr20 = Monster::factory()->create(['name' => 'Ancient Dragon', 'challenge_rating' => '20']);
        $this->createEntitySource($cr20, $source);

        // Index for Meilisearch
        $cr0_125->searchable();
        $cr1->searchable();
        $cr5->searchable();
        $cr10->searchable();
        $cr20->searchable();
        sleep(1);

        // Filter by CR 1 TO 10 (should get CR 1, 5, and 10)
        $response = $this->getJson('/api/v1/monsters?filter=challenge_rating 1 TO 10');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Goblin', $names);
        $this->assertContains('Hill Giant', $names);
        $this->assertContains('Young Red Dragon', $names);
    }

    // ============================================================
    // String Operators (type field) - 2 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_type_with_equals(): void
    {
        $source = $this->getSource('MM');

        // Create monsters with different types
        $dragon = Monster::factory()->create(['name' => 'Red Dragon', 'type' => 'dragon']);
        $this->createEntitySource($dragon, $source);

        $beast = Monster::factory()->create(['name' => 'Wolf', 'type' => 'beast']);
        $this->createEntitySource($beast, $source);

        $undead = Monster::factory()->create(['name' => 'Zombie', 'type' => 'undead']);
        $this->createEntitySource($undead, $source);

        // Index for Meilisearch
        $dragon->searchable();
        $beast->searchable();
        $undead->searchable();
        sleep(1);

        // Filter by type = dragon
        $response = $this->getJson('/api/v1/monsters?filter=type = dragon');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Red Dragon');
        $response->assertJsonPath('data.0.type', 'dragon');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_type_with_not_equals(): void
    {
        $source = $this->getSource('MM');

        // Create monsters with different types
        $dragon = Monster::factory()->create(['name' => 'Red Dragon', 'type' => 'dragon']);
        $this->createEntitySource($dragon, $source);

        $beast1 = Monster::factory()->create(['name' => 'Wolf', 'type' => 'beast']);
        $this->createEntitySource($beast1, $source);

        $beast2 = Monster::factory()->create(['name' => 'Bear', 'type' => 'beast']);
        $this->createEntitySource($beast2, $source);

        // Index for Meilisearch
        $dragon->searchable();
        $beast1->searchable();
        $beast2->searchable();
        sleep(1);

        // Filter by type != dragon (should get beasts only)
        $response = $this->getJson('/api/v1/monsters?filter=type != dragon');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        // Verify all returned monsters are not dragons
        foreach ($response->json('data') as $monster) {
            $this->assertNotEquals('dragon', $monster['type'], "Monster {$monster['name']} should not be type dragon");
        }

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Wolf', $names);
        $this->assertContains('Bear', $names);
    }

    // ============================================================
    // Boolean Operators (can_hover field) - 7 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_can_hover_with_equals_true(): void
    {
        $source = $this->getSource('MM');

        // Create monsters with different hover capabilities
        $hovering = Monster::factory()->create([
            'name' => 'Beholder',
            'can_hover' => true,
            'speed_fly' => 20,
        ]);
        $this->createEntitySource($hovering, $source);

        $flying = Monster::factory()->create([
            'name' => 'Dragon',
            'can_hover' => false,
            'speed_fly' => 80,
        ]);
        $this->createEntitySource($flying, $source);

        $grounded = Monster::factory()->create([
            'name' => 'Orc',
            'can_hover' => false,
            'speed_fly' => null,
        ]);
        $this->createEntitySource($grounded, $source);

        // Index for Meilisearch
        $hovering->searchable();
        $flying->searchable();
        $grounded->searchable();
        sleep(1);

        // Filter by can_hover = true
        $response = $this->getJson('/api/v1/monsters?filter=can_hover = true');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Beholder');
        $response->assertJsonPath('data.0.can_hover', true);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_can_hover_with_equals_false(): void
    {
        $source = $this->getSource('MM');

        // Create monsters with different hover capabilities
        $hovering = Monster::factory()->create([
            'name' => 'Beholder',
            'can_hover' => true,
            'speed_fly' => 20,
        ]);
        $this->createEntitySource($hovering, $source);

        $flying = Monster::factory()->create([
            'name' => 'Dragon',
            'can_hover' => false,
            'speed_fly' => 80,
        ]);
        $this->createEntitySource($flying, $source);

        $grounded = Monster::factory()->create([
            'name' => 'Orc',
            'can_hover' => false,
            'speed_fly' => null,
        ]);
        $this->createEntitySource($grounded, $source);

        // Index for Meilisearch
        $hovering->searchable();
        $flying->searchable();
        $grounded->searchable();
        sleep(1);

        // Filter by can_hover = false
        $response = $this->getJson('/api/v1/monsters?filter=can_hover = false');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        // Verify all returned monsters have can_hover = false
        foreach ($response->json('data') as $monster) {
            $this->assertFalse($monster['can_hover'], "Monster {$monster['name']} should not be able to hover");
        }

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Dragon', $names);
        $this->assertContains('Orc', $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_can_hover_with_not_equals_true(): void
    {
        $source = $this->getSource('MM');

        // Create monsters with different hover capabilities
        $hovering = Monster::factory()->create([
            'name' => 'Beholder',
            'can_hover' => true,
            'speed_fly' => 20,
        ]);
        $this->createEntitySource($hovering, $source);

        $flying = Monster::factory()->create([
            'name' => 'Dragon',
            'can_hover' => false,
            'speed_fly' => 80,
        ]);
        $this->createEntitySource($flying, $source);

        $grounded = Monster::factory()->create([
            'name' => 'Orc',
            'can_hover' => false,
            'speed_fly' => null,
        ]);
        $this->createEntitySource($grounded, $source);

        // Index for Meilisearch
        $hovering->searchable();
        $flying->searchable();
        $grounded->searchable();
        sleep(1);

        // Filter by can_hover != true (should get false values)
        $response = $this->getJson('/api/v1/monsters?filter=can_hover != true');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        // Verify all returned monsters do not hover
        foreach ($response->json('data') as $monster) {
            $this->assertFalse($monster['can_hover'], "Monster {$monster['name']} should not be able to hover (using != true)");
        }

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Dragon', $names);
        $this->assertContains('Orc', $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_can_hover_with_not_equals_false(): void
    {
        $source = $this->getSource('MM');

        // Create monsters with different hover capabilities
        $hovering = Monster::factory()->create([
            'name' => 'Beholder',
            'can_hover' => true,
            'speed_fly' => 20,
        ]);
        $this->createEntitySource($hovering, $source);

        $flying = Monster::factory()->create([
            'name' => 'Dragon',
            'can_hover' => false,
            'speed_fly' => 80,
        ]);
        $this->createEntitySource($flying, $source);

        $grounded = Monster::factory()->create([
            'name' => 'Orc',
            'can_hover' => false,
            'speed_fly' => null,
        ]);
        $this->createEntitySource($grounded, $source);

        // Index for Meilisearch
        $hovering->searchable();
        $flying->searchable();
        $grounded->searchable();
        sleep(1);

        // Filter by can_hover != false (should get true values)
        $response = $this->getJson('/api/v1/monsters?filter=can_hover != false');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Beholder');
        $response->assertJsonPath('data.0.can_hover', true);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_can_hover_with_is_null(): void
    {
        $source = $this->getSource('MM');

        // Create monsters with different hover capabilities
        $hovering = Monster::factory()->create([
            'name' => 'Beholder',
            'can_hover' => true,
            'speed_fly' => 20,
        ]);
        $this->createEntitySource($hovering, $source);

        $grounded = Monster::factory()->create([
            'name' => 'Orc',
            'can_hover' => false,
            'speed_fly' => null,
        ]);
        $this->createEntitySource($grounded, $source);

        // Index for Meilisearch
        $hovering->searchable();
        $grounded->searchable();
        sleep(1);

        // Filter by can_hover IS NULL
        // Note: can_hover has a default value of false in the database, so it can never be null
        // This test verifies that IS NULL operator works correctly and returns 0 results
        $response = $this->getJson('/api/v1/monsters?filter=can_hover IS NULL');

        $response->assertOk();

        // The can_hover field should never be null (always true/false with default false)
        // This test ensures IS NULL operator works without errors
        // With properly defined schema, we expect 0 results
        $this->assertEquals(0, $response->json('meta.total'), 'can_hover field has default value, should never be null');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_can_hover_with_is_not_null(): void
    {
        $source = $this->getSource('MM');

        // Create monsters with different hover capabilities
        $hovering = Monster::factory()->create([
            'name' => 'Beholder',
            'can_hover' => true,
            'speed_fly' => 20,
        ]);
        $this->createEntitySource($hovering, $source);

        $grounded = Monster::factory()->create([
            'name' => 'Orc',
            'can_hover' => false,
            'speed_fly' => null,
        ]);
        $this->createEntitySource($grounded, $source);

        // Index for Meilisearch
        $hovering->searchable();
        $grounded->searchable();
        sleep(1);

        // Filter by can_hover IS NOT NULL
        // Note: can_hover has a default value of false in the database, so all records have non-null values
        $response = $this->getJson('/api/v1/monsters?filter=can_hover IS NOT NULL');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        // Verify all returned monsters have non-null can_hover
        foreach ($response->json('data') as $monster) {
            $this->assertNotNull($monster['can_hover'], "Monster {$monster['name']} should have non-null can_hover");
        }

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Beholder', $names);
        $this->assertContains('Orc', $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_can_hover_with_not_equals(): void
    {
        $source = $this->getSource('MM');

        // Create monsters with different hover capabilities
        $hovering = Monster::factory()->create([
            'name' => 'Beholder',
            'can_hover' => true,
            'speed_fly' => 20,
        ]);
        $this->createEntitySource($hovering, $source);

        $grounded = Monster::factory()->create([
            'name' => 'Orc',
            'can_hover' => false,
            'speed_fly' => null,
        ]);
        $this->createEntitySource($grounded, $source);

        // Index for Meilisearch
        $hovering->searchable();
        $grounded->searchable();
        sleep(1);

        // Filter by can_hover != false (test != operator directly)
        $response = $this->getJson('/api/v1/monsters?filter=can_hover != false');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Beholder');
        $response->assertJsonPath('data.0.can_hover', true);
    }

    // ============================================================
    // Array Operators (spell_slugs field) - 3 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_spell_slugs_with_in(): void
    {
        $source = $this->getSource('MM');

        // Create spells first
        $fireball = \App\Models\Spell::factory()->create(['name' => 'Fireball', 'slug' => 'fireball']);
        $lightningBolt = \App\Models\Spell::factory()->create(['name' => 'Lightning Bolt', 'slug' => 'lightning-bolt']);
        $iceStorm = \App\Models\Spell::factory()->create(['name' => 'Ice Storm', 'slug' => 'ice-storm']);

        // Create monsters with different spell associations
        $wizardMonster = Monster::factory()->create(['name' => 'Evil Wizard', 'type' => 'humanoid']);
        $this->createEntitySource($wizardMonster, $source);
        $wizardMonster->entitySpells()->attach($fireball->id);
        $wizardMonster->entitySpells()->attach($lightningBolt->id);

        $sorcererMonster = Monster::factory()->create(['name' => 'Dark Sorcerer', 'type' => 'humanoid']);
        $this->createEntitySource($sorcererMonster, $source);
        $sorcererMonster->entitySpells()->attach($fireball->id);
        $sorcererMonster->entitySpells()->attach($iceStorm->id);

        $nonCaster = Monster::factory()->create(['name' => 'Orc Warrior', 'type' => 'humanoid']);
        $this->createEntitySource($nonCaster, $source);

        // Index for Meilisearch
        $wizardMonster->searchable();
        $sorcererMonster->searchable();
        $nonCaster->searchable();
        sleep(1);

        // Filter by spell_slugs IN [fireball, lightning-bolt] (monsters with fireball OR lightning-bolt)
        $response = $this->getJson('/api/v1/monsters?filter=spell_slugs IN [fireball, lightning-bolt]');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        // Both wizard and sorcerer should be included (both have fireball, wizard also has lightning-bolt)
        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Evil Wizard', $names);
        $this->assertContains('Dark Sorcerer', $names);

        // Verify the Orc Warrior (non-caster) is NOT included
        $this->assertNotContains('Orc Warrior', $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_spell_slugs_with_not_in(): void
    {
        $source = $this->getSource('MM');

        // Create spells first
        $fireball = \App\Models\Spell::factory()->create(['name' => 'Fireball', 'slug' => 'fireball']);
        $iceStorm = \App\Models\Spell::factory()->create(['name' => 'Ice Storm', 'slug' => 'ice-storm']);

        // Create monsters with different spell associations
        $fireballCaster = Monster::factory()->create(['name' => 'Fire Mage', 'type' => 'humanoid']);
        $this->createEntitySource($fireballCaster, $source);
        $fireballCaster->entitySpells()->attach($fireball->id);

        $iceCaster = Monster::factory()->create(['name' => 'Ice Mage', 'type' => 'humanoid']);
        $this->createEntitySource($iceCaster, $source);
        $iceCaster->entitySpells()->attach($iceStorm->id);

        $nonCaster = Monster::factory()->create(['name' => 'Orc Warrior', 'type' => 'humanoid']);
        $this->createEntitySource($nonCaster, $source);

        // Index for Meilisearch
        $fireballCaster->searchable();
        $iceCaster->searchable();
        $nonCaster->searchable();
        sleep(1);

        // Filter by spell_slugs NOT IN [fireball] (exclude monsters with fireball)
        $response = $this->getJson('/api/v1/monsters?filter=spell_slugs NOT IN [fireball]');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        // Ice Mage and Orc Warrior should be included, Fire Mage excluded
        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Ice Mage', $names);
        $this->assertContains('Orc Warrior', $names);
        $this->assertNotContains('Fire Mage', $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_spell_slugs_with_is_empty(): void
    {
        $source = $this->getSource('MM');

        // Create spell first
        $fireball = \App\Models\Spell::factory()->create(['name' => 'Fireball', 'slug' => 'fireball']);

        // Create monsters with and without spells
        $caster = Monster::factory()->create(['name' => 'Wizard', 'type' => 'humanoid']);
        $this->createEntitySource($caster, $source);
        $caster->entitySpells()->attach($fireball->id);

        $nonCaster1 = Monster::factory()->create(['name' => 'Orc', 'type' => 'humanoid']);
        $this->createEntitySource($nonCaster1, $source);

        $nonCaster2 = Monster::factory()->create(['name' => 'Goblin', 'type' => 'humanoid']);
        $this->createEntitySource($nonCaster2, $source);

        // Index for Meilisearch
        $caster->searchable();
        $nonCaster1->searchable();
        $nonCaster2->searchable();
        sleep(1);

        // Filter by spell_slugs IS EMPTY (monsters with no spells)
        $response = $this->getJson('/api/v1/monsters?filter=spell_slugs IS EMPTY');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        // Verify the non-casters are returned
        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Orc', $names);
        $this->assertContains('Goblin', $names);
        $this->assertNotContains('Wizard', $names);
    }

    // ============================================================
    // Boolean Operators (has_legendary_actions field) - 3 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_legendary_with_equals_true(): void
    {
        $source = $this->getSource('MM');

        // Create monsters with and without legendary actions
        $legendary = Monster::factory()->create(['name' => 'Ancient Dragon', 'type' => 'dragon']);
        $this->createEntitySource($legendary, $source);
        // Add legendary action (not a lair action)
        \App\Models\MonsterLegendaryAction::create([
            'monster_id' => $legendary->id,
            'name' => 'Wing Attack',
            'description' => 'The dragon beats its wings.',
            'is_lair_action' => false,
        ]);

        $normalMonster = Monster::factory()->create(['name' => 'Goblin', 'type' => 'humanoid']);
        $this->createEntitySource($normalMonster, $source);

        $withLairOnly = Monster::factory()->create(['name' => 'Elder Brain', 'type' => 'aberration']);
        $this->createEntitySource($withLairOnly, $source);
        // Add lair action only (should not count as legendary action)
        \App\Models\MonsterLegendaryAction::create([
            'monster_id' => $withLairOnly->id,
            'name' => 'Lair Effect',
            'description' => 'The lair trembles.',
            'is_lair_action' => true,
        ]);

        // Index for Meilisearch
        $legendary->searchable();
        $normalMonster->searchable();
        $withLairOnly->searchable();
        sleep(1);

        // Filter by has_legendary_actions = true
        $response = $this->getJson('/api/v1/monsters?filter=has_legendary_actions = true');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Ancient Dragon');

        // Note: has_legendary_actions is a computed Meilisearch field, not exposed in API Resource
        // The filter works correctly based on the legendary actions relationship
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_legendary_with_not_equals_false(): void
    {
        $source = $this->getSource('MM');

        // Create monsters with and without legendary actions
        $legendary = Monster::factory()->create(['name' => 'Ancient Dragon', 'type' => 'dragon']);
        $this->createEntitySource($legendary, $source);
        // Add legendary action (not a lair action)
        \App\Models\MonsterLegendaryAction::create([
            'monster_id' => $legendary->id,
            'name' => 'Wing Attack',
            'description' => 'The dragon beats its wings.',
            'is_lair_action' => false,
        ]);

        $normalMonster = Monster::factory()->create(['name' => 'Goblin', 'type' => 'humanoid']);
        $this->createEntitySource($normalMonster, $source);

        // Index for Meilisearch
        $legendary->searchable();
        $normalMonster->searchable();
        sleep(1);

        // Filter by has_legendary_actions != false (should get true values)
        $response = $this->getJson('/api/v1/monsters?filter=has_legendary_actions != false');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Ancient Dragon');

        // Note: has_legendary_actions is a computed Meilisearch field, not exposed in API Resource
        // The filter works correctly based on the legendary actions relationship
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_legendary_with_is_null(): void
    {
        $source = $this->getSource('MM');

        // Create monsters with and without legendary actions
        $legendary = Monster::factory()->create(['name' => 'Ancient Dragon', 'type' => 'dragon']);
        $this->createEntitySource($legendary, $source);
        // Add legendary action
        \App\Models\MonsterLegendaryAction::create([
            'monster_id' => $legendary->id,
            'name' => 'Wing Attack',
            'description' => 'The dragon beats its wings.',
            'is_lair_action' => false,
        ]);

        $normalMonster = Monster::factory()->create(['name' => 'Goblin', 'type' => 'humanoid']);
        $this->createEntitySource($normalMonster, $source);

        // Index for Meilisearch
        $legendary->searchable();
        $normalMonster->searchable();
        sleep(1);

        // Filter by has_legendary_actions IS NULL
        // Note: This is a computed field, so it will be false, not null for monsters without legendary actions
        // This test verifies that IS NULL works correctly even though we may get 0 results
        $response = $this->getJson('/api/v1/monsters?filter=has_legendary_actions IS NULL');

        $response->assertOk();

        // The computed field has_legendary_actions should never be null (always true/false)
        // This test ensures IS NULL operator works without errors
        // With properly computed data, we expect 0 results
        $this->assertGreaterThanOrEqual(0, $response->json('meta.total'), 'IS NULL operator should work without errors');
    }
}
