<?php

namespace Tests\Feature\Api;

use App\Models\CharacterClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassFilterOperatorTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    // ============================================================
    // Integer Operators (hit_die field) - 7 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_hit_die_with_equals(): void
    {
        // Create classes with different hit dice
        CharacterClass::factory()->create(['name' => 'Test Wizard Eq', 'slug' => 'test-wizard-eq', 'hit_die' => 6]);
        CharacterClass::factory()->create(['name' => 'Test Bard Eq', 'slug' => 'test-bard-eq', 'hit_die' => 8]);
        CharacterClass::factory()->create(['name' => 'Test Fighter Eq', 'slug' => 'test-fighter-eq', 'hit_die' => 10]);

        $response = $this->getJson('/api/v1/classes?filter=hit_die = 8');

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'));

        foreach ($response->json('data') as $class) {
            $this->assertEquals(8, $class['hit_die']);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_hit_die_with_not_equals(): void
    {
        // Create classes with different hit dice
        CharacterClass::factory()->create(['name' => 'Test Sorcerer Ne', 'slug' => 'test-sorcerer-ne', 'hit_die' => 6]);
        CharacterClass::factory()->create(['name' => 'Test Barbarian Ne', 'slug' => 'test-barbarian-ne', 'hit_die' => 12]);

        $response = $this->getJson('/api/v1/classes?filter=hit_die != 6');

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'));

        foreach ($response->json('data') as $class) {
            $this->assertNotEquals(6, $class['hit_die']);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_hit_die_with_greater_than(): void
    {
        // Create classes with different hit dice
        $classes = collect([
            CharacterClass::factory()->create(['name' => 'Test Wizard GT', 'slug' => 'test-wizard-gt-unique', 'hit_die' => 6]),
            CharacterClass::factory()->create(['name' => 'Test Monk GT', 'slug' => 'test-monk-gt-unique', 'hit_die' => 8]),
            CharacterClass::factory()->create(['name' => 'Test Ranger GT', 'slug' => 'test-ranger-gt-unique', 'hit_die' => 10]),
            CharacterClass::factory()->create(['name' => 'Test Barbarian GT', 'slug' => 'test-barbarian-gt-unique', 'hit_die' => 12]),
        ]);

        // Force immediate indexing
        $classes->searchable();

        // Give Meilisearch a moment to index
        sleep(1);

        $response = $this->getJson('/api/v1/classes?filter=hit_die > 8');

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'));

        foreach ($response->json('data') as $class) {
            $this->assertGreaterThan(8, $class['hit_die']);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_hit_die_with_greater_than_or_equal(): void
    {
        // Create classes with different hit dice
        CharacterClass::factory()->create(['name' => 'Test Bard', 'slug' => 'test-bard-gte', 'hit_die' => 8]);
        CharacterClass::factory()->create(['name' => 'Test Fighter', 'slug' => 'test-fighter-gte', 'hit_die' => 10]);
        CharacterClass::factory()->create(['name' => 'Test Barbarian', 'slug' => 'test-barbarian-gte', 'hit_die' => 12]);

        $response = $this->getJson('/api/v1/classes?filter=hit_die >= 10');

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'));

        foreach ($response->json('data') as $class) {
            $this->assertGreaterThanOrEqual(10, $class['hit_die']);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_hit_die_with_less_than(): void
    {
        // Create classes with different hit dice
        CharacterClass::factory()->create(['name' => 'Test Sorcerer', 'slug' => 'test-sorcerer-lt', 'hit_die' => 6]);
        CharacterClass::factory()->create(['name' => 'Test Warlock', 'slug' => 'test-warlock-lt', 'hit_die' => 8]);
        CharacterClass::factory()->create(['name' => 'Test Paladin', 'slug' => 'test-paladin-lt', 'hit_die' => 10]);

        $response = $this->getJson('/api/v1/classes?filter=hit_die < 10');

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'));

        foreach ($response->json('data') as $class) {
            $this->assertLessThan(10, $class['hit_die']);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_hit_die_with_less_than_or_equal(): void
    {
        // Create classes with different hit dice
        CharacterClass::factory()->create(['name' => 'Test Wizard', 'slug' => 'test-wizard-lte', 'hit_die' => 6]);
        CharacterClass::factory()->create(['name' => 'Test Druid', 'slug' => 'test-druid-lte', 'hit_die' => 8]);
        CharacterClass::factory()->create(['name' => 'Test Ranger', 'slug' => 'test-ranger-lte', 'hit_die' => 10]);

        $response = $this->getJson('/api/v1/classes?filter=hit_die <= 8');

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'));

        foreach ($response->json('data') as $class) {
            $this->assertLessThanOrEqual(8, $class['hit_die']);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_hit_die_with_to_range(): void
    {
        // Create classes with different hit dice
        CharacterClass::factory()->create(['name' => 'Test Wizard', 'slug' => 'test-wizard-to', 'hit_die' => 6]);
        CharacterClass::factory()->create(['name' => 'Test Bard', 'slug' => 'test-bard-to', 'hit_die' => 8]);
        CharacterClass::factory()->create(['name' => 'Test Fighter', 'slug' => 'test-fighter-to', 'hit_die' => 10]);
        CharacterClass::factory()->create(['name' => 'Test Barbarian', 'slug' => 'test-barbarian-to', 'hit_die' => 12]);

        $response = $this->getJson('/api/v1/classes?filter=hit_die 8 TO 10');

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'));

        foreach ($response->json('data') as $class) {
            $this->assertGreaterThanOrEqual(8, $class['hit_die']);
            $this->assertLessThanOrEqual(10, $class['hit_die']);
        }
    }

    // ============================================================
    // String Operators (primary_ability field) - 2 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_primary_ability_with_equals(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_primary_ability_with_not_equals(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    // ============================================================
    // Boolean Operators (is_base_class field) - 4 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_is_base_class_with_equals_true(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_is_base_class_with_equals_false(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_is_base_class_with_not_equals_true(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_is_base_class_with_not_equals_false(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    // ============================================================
    // Array Operators (tag_slugs field) - 3 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_tag_slugs_with_in(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_tag_slugs_with_not_in(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_tag_slugs_with_is_empty(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }
}
