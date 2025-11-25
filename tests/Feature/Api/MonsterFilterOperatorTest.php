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
        $this->markTestIncomplete('Not implemented yet');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_type_with_not_equals(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    // ============================================================
    // Boolean Operators (can_hover field) - 5 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_can_hover_with_equals_true(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_can_hover_with_equals_false(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_can_hover_with_not_equals_true(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_can_hover_with_not_equals_false(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_can_hover_with_is_null(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    // ============================================================
    // Array Operators (spell_slugs field) - 3 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_spell_slugs_with_in(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_spell_slugs_with_not_in(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_spell_slugs_with_is_empty(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    // ============================================================
    // Boolean Operators (legendary field) - 3 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_legendary_with_equals_true(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_legendary_with_not_equals_false(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_legendary_with_is_null(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }
}
