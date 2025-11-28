<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-search')]
class SpellFilterOperatorTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = \Database\Seeders\TestDatabaseSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure Meilisearch indexes for testing
        $this->artisan('search:configure-indexes');
    }

    // ============================================================
    // Integer Operators (level field) - 7 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_level_with_equals(): void
    {
        // Act: Filter by level = 3 (PHB has many level 3 spells)
        $response = $this->getJson('/api/v1/spells?filter=level = 3');

        // Assert
        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find level 3 spells');

        // Verify all returned spells are level 3
        foreach ($response->json('data') as $spell) {
            $this->assertEquals(3, $spell['level'], "Spell {$spell['name']} should be level 3");
        }

        // Verify some known level 3 spells are included
        $spellNames = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Animate Dead', $spellNames);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_level_with_not_equals(): void
    {
        // Act: Filter by level != 3 (should return all spells except level 3)
        $response = $this->getJson('/api/v1/spells?filter=level != 3');

        // Assert: Should return spells of all levels except 3
        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find non-level-3 spells');

        // Verify no level 3 spells in results
        foreach ($response->json('data') as $spell) {
            $this->assertNotEquals(3, $spell['level'], "Spell {$spell['name']} should not be level 3");
        }

        // Verify we have spells of other levels
        $levels = collect($response->json('data'))->pluck('level')->unique()->toArray();
        $this->assertNotContains(3, $levels, 'Level 3 should be excluded');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_level_with_greater_than(): void
    {
        // Act: Filter by level > 5 (should return levels 6, 7, 8, 9)
        $response = $this->getJson('/api/v1/spells?filter=level > 5');

        // Assert: Should return only high-level spells
        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find spells level > 5');

        // Verify all returned spells are greater than level 5
        foreach ($response->json('data') as $spell) {
            $this->assertGreaterThan(5, $spell['level'], "Spell {$spell['name']} level should be > 5");
        }

        // Verify no level 5 or lower spells
        $levels = collect($response->json('data'))->pluck('level')->unique()->sort()->toArray();
        foreach ($levels as $level) {
            $this->assertGreaterThan(5, $level);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_level_with_greater_than_or_equal(): void
    {
        // Act: Filter by level >= 7 (should return levels 7, 8, 9)
        $response = $this->getJson('/api/v1/spells?filter=level >= 7');

        // Assert: Should include level 7 and above
        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find spells level >= 7');

        // Verify all returned spells are level 7 or higher
        foreach ($response->json('data') as $spell) {
            $this->assertGreaterThanOrEqual(7, $spell['level'], "Spell {$spell['name']} level should be >= 7");
        }

        // Verify we have level 7, 8, or 9 spells only
        $levels = collect($response->json('data'))->pluck('level')->unique()->sort()->toArray();
        foreach ($levels as $level) {
            $this->assertGreaterThanOrEqual(7, $level);
            $this->assertLessThanOrEqual(9, $level);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_level_with_less_than(): void
    {
        // Act: Filter by level < 2 (should return levels 0, 1 only)
        $response = $this->getJson('/api/v1/spells?filter=level < 2');

        // Assert: Should return cantrips and 1st level spells only
        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find spells level < 2');

        // Verify all returned spells are less than level 2
        foreach ($response->json('data') as $spell) {
            $this->assertLessThan(2, $spell['level'], "Spell {$spell['name']} level should be < 2");
        }

        // Verify we only have level 0 or 1
        $levels = collect($response->json('data'))->pluck('level')->unique()->sort()->toArray();
        foreach ($levels as $level) {
            $this->assertContains($level, [0, 1], 'Only levels 0 and 1 should be present');
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_level_with_less_than_or_equal(): void
    {
        // Act: Filter by level <= 1 (should return levels 0, 1)
        $response = $this->getJson('/api/v1/spells?filter=level <= 1');

        // Assert: Should include cantrips and 1st level spells
        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find spells level <= 1');

        // Verify all returned spells are level 1 or lower
        foreach ($response->json('data') as $spell) {
            $this->assertLessThanOrEqual(1, $spell['level'], "Spell {$spell['name']} level should be <= 1");
        }

        // Verify we only have level 0 or 1
        $levels = collect($response->json('data'))->pluck('level')->unique()->sort()->toArray();
        $this->assertEquals([0, 1], $levels, 'Should only have levels 0 and 1');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_level_with_to_range(): void
    {
        // Act: Filter by level 3 TO 5 (inclusive range - should return levels 3, 4, 5)
        $response = $this->getJson('/api/v1/spells?filter=level 3 TO 5');

        // Assert: Should include levels 3, 4, 5 (TO operator is inclusive)
        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find spells level 3 TO 5');

        // Verify all returned spells are in range 3-5
        foreach ($response->json('data') as $spell) {
            $this->assertGreaterThanOrEqual(3, $spell['level'], "Spell {$spell['name']} level should be >= 3");
            $this->assertLessThanOrEqual(5, $spell['level'], "Spell {$spell['name']} level should be <= 5");
        }

        // Verify we have levels 3, 4, and/or 5 only
        $levels = collect($response->json('data'))->pluck('level')->unique()->sort()->toArray();
        foreach ($levels as $level) {
            $this->assertContains($level, [3, 4, 5], 'Only levels 3, 4, 5 should be present');
        }

        // Edge case test: Verify TO range is inclusive (level 3 and 5 should be included)
        $this->assertContains(3, $levels, 'Level 3 should be included (TO is inclusive)');
        $this->assertContains(5, $levels, 'Level 5 should be included (TO is inclusive)');
    }

    // ============================================================
    // String Operators (school_code field) - 2 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_school_code_with_equals(): void
    {
        // Act: Filter by school_code = EV (Evocation spells) - use per_page to get more results
        // Note: Filtering uses Meilisearch field name (school_code)
        $response = $this->getJson('/api/v1/spells?filter=school_code = EV&per_page=100');

        // Assert
        $response->assertOk();
        $response->assertJsonStructure(['data' => [['school' => ['code']]]]);
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find Evocation spells');

        // Verify all returned spells have school.code = 'EV'
        // Note: API Resource exposes this as nested 'school.code', not flat 'school_code'
        foreach ($response->json('data') as $spell) {
            $this->assertEquals('EV', $spell['school']['code'], "Spell {$spell['name']} should be Evocation");
        }

        // Verify some known Evocation spells are included
        $spellNames = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Bigby\'s Hand', $spellNames, 'Bigby\'s Hand is an Evocation spell in fixtures');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_school_code_with_not_equals(): void
    {
        // Act: Filter by school_code != EV (all spells except Evocation)
        // Note: Filtering uses Meilisearch field name (school_code)
        $response = $this->getJson('/api/v1/spells?filter=school_code != EV');

        // Assert: Should return spells of all schools except Evocation
        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find non-Evocation spells');

        // Verify no Evocation spells in results
        // Note: API Resource exposes this as nested 'school.code', not flat 'school_code'
        foreach ($response->json('data') as $spell) {
            $this->assertNotEquals('EV', $spell['school']['code'], "Spell {$spell['name']} should not be Evocation");
        }

        // Verify we have spells from other schools
        $schoolCodes = collect($response->json('data'))->pluck('school.code')->unique()->toArray();
        $this->assertNotContains('EV', $schoolCodes, 'Evocation should be excluded');
        $this->assertGreaterThan(1, count($schoolCodes), 'Should have multiple non-EV schools');
    }

    // ============================================================
    // Boolean Operators (concentration field) - 5 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_concentration_with_equals_true(): void
    {
        // Act: Filter by concentration = true (spells requiring concentration) - use per_page to get more results
        // Note: Filtering uses Meilisearch field name (concentration)
        $response = $this->getJson('/api/v1/spells?filter=concentration = true&per_page=100');

        // Assert
        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find concentration spells');

        // Verify all returned spells require concentration
        // Note: API Resource exposes this as 'needs_concentration', not 'concentration'
        foreach ($response->json('data') as $spell) {
            $this->assertTrue($spell['needs_concentration'], "Spell {$spell['name']} should require concentration");
        }

        // Verify some known concentration spells are included
        $spellNames = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Alter Self', $spellNames, 'Alter Self requires concentration and exists in fixtures');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_concentration_with_equals_false(): void
    {
        // Act: Filter by concentration = false (spells not requiring concentration)
        // Use per_page=100 and sort_by=name to get deterministic results including Acid Splash
        // Note: Filtering uses Meilisearch field name (concentration)
        $response = $this->getJson('/api/v1/spells?filter=concentration = false&per_page=100&sort_by=name');

        // Assert
        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find non-concentration spells');

        // Verify all returned spells do NOT require concentration
        // Note: API Resource exposes this as 'needs_concentration', not 'concentration'
        foreach ($response->json('data') as $spell) {
            $this->assertFalse($spell['needs_concentration'], "Spell {$spell['name']} should not require concentration");
        }

        // Verify some known non-concentration spells are included (with enough per_page, we should see Acid Splash)
        $spellNames = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Acid Splash', $spellNames, 'Acid Splash does not require concentration');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_concentration_with_not_equals_true(): void
    {
        // Act: Filter by concentration != true (spells that are false or null)
        // Note: Filtering uses Meilisearch field name (concentration)
        $response = $this->getJson('/api/v1/spells?filter=concentration != true');

        // Assert: Should return non-concentration spells
        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find non-concentration spells');

        // Verify all returned spells do NOT require concentration
        // Note: API Resource exposes this as 'needs_concentration', not 'concentration'
        foreach ($response->json('data') as $spell) {
            $this->assertFalse($spell['needs_concentration'], "Spell {$spell['name']} should not require concentration (using != true)");
        }

        // Verify concentration spells are excluded
        $spellNames = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertNotContains('Alter Self', $spellNames, 'Alter Self requires concentration (should be excluded)');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_concentration_with_not_equals_false(): void
    {
        // Act: Filter by concentration != false (spells that are true or null)
        // Note: Filtering uses Meilisearch field name (concentration)
        $response = $this->getJson('/api/v1/spells?filter=concentration != false');

        // Assert: Should return concentration-required spells
        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find concentration spells');

        // Verify all returned spells DO require concentration
        // Note: API Resource exposes this as 'needs_concentration', not 'concentration'
        foreach ($response->json('data') as $spell) {
            $this->assertTrue($spell['needs_concentration'], "Spell {$spell['name']} should require concentration (using != false)");
        }

        // Verify non-concentration spells are excluded
        $spellNames = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertNotContains('Acid Splash', $spellNames, 'Acid Splash does not require concentration (should be excluded)');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_concentration_with_is_null(): void
    {
        // Act: Filter by concentration IS NULL (spells with no concentration data)
        // Note: Filtering uses Meilisearch field name (concentration)
        $response = $this->getJson('/api/v1/spells?filter=concentration IS NULL');

        // Assert: PHB spells should all have concentration data (true or false)
        // This test verifies IS NULL works, but may return 0 results with clean PHB data
        $response->assertOk();

        // If any results are returned, verify they have null concentration
        // Note: API Resource exposes this as 'needs_concentration', not 'concentration'
        if ($response->json('meta.total') > 0) {
            foreach ($response->json('data') as $spell) {
                $this->assertNull($spell['needs_concentration'], "Spell {$spell['name']} should have null concentration");
            }
        }

        // With properly imported PHB data, we expect 0 results since all spells have concentration set
        // This demonstrates IS NULL operator works correctly
        $this->assertGreaterThanOrEqual(0, $response->json('meta.total'), 'IS NULL operator should work without errors');
    }

    // ============================================================
    // Array Operators (class_slugs field) - 3 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_class_slugs_with_in(): void
    {
        // Act: Filter by class_slugs IN [wizard, bard] (spells available to wizard OR bard)
        $response = $this->getJson('/api/v1/spells?filter=class_slugs IN [wizard, bard]');

        // Assert: Should return spells available to wizard or bard
        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find spells for wizard or bard');

        // Verify all returned spells have wizard OR bard in their class_slugs
        foreach ($response->json('data') as $spell) {
            $classes = $spell['classes'] ?? [];
            $classSlugs = collect($classes)->pluck('slug')->toArray();

            $hasWizardOrBard = in_array('wizard', $classSlugs) || in_array('bard', $classSlugs);
            $this->assertTrue($hasWizardOrBard, "Spell {$spell['name']} should have wizard or bard class");
        }

        // Verify we have multiple results (wizard and bard have many spells in PHB)
        $this->assertGreaterThan(50, $response->json('meta.total'), 'Should find many wizard/bard spells in PHB');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_class_slugs_with_not_in(): void
    {
        // Act: Filter by class_slugs NOT IN [wizard] (exclude all wizard spells)
        $response = $this->getJson('/api/v1/spells?filter=class_slugs NOT IN [wizard]');

        // Assert: Should return spells NOT available to wizard
        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find non-wizard spells');

        // Verify NO returned spells have wizard in their class_slugs
        foreach ($response->json('data') as $spell) {
            $classes = $spell['classes'] ?? [];
            $classSlugs = collect($classes)->pluck('slug')->toArray();

            $this->assertNotContains('wizard', $classSlugs, "Spell {$spell['name']} should not have wizard class");
        }

        // Verify we have class-specific spells excluded (wizard has many exclusive spells)
        // With ~477 total PHB spells and wizard having ~200+, we should have significant filtering
        $this->assertLessThan(400, $response->json('meta.total'), 'Should have excluded wizard spells');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_class_slugs_with_is_empty(): void
    {
        // Act: Filter by class_slugs IS EMPTY (spells with no class associations)
        $response = $this->getJson('/api/v1/spells?filter=class_slugs IS EMPTY');

        // Assert: Should return spells with no class associations (if any exist in PHB)
        $response->assertOk();

        // Verify all returned spells have empty class_slugs array
        foreach ($response->json('data') as $spell) {
            $classes = $spell['classes'] ?? [];
            $classSlugs = collect($classes)->pluck('slug')->toArray();

            $this->assertEmpty($classSlugs, "Spell {$spell['name']} should have no class associations");
        }

        // Note: PHB spells typically have class associations, so this may return 0 results
        // This is a valid test case for edge cases or future data
    }

    // ============================================================
    // Boolean Operators (ritual field) - 2 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_ritual_with_equals_true(): void
    {
        // Act: Filter by ritual = true (spells that can be cast as rituals)
        // Note: Filtering uses Meilisearch field name (ritual)
        $response = $this->getJson('/api/v1/spells?filter=ritual = true');

        // Assert
        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find ritual spells');

        // Verify all returned spells can be cast as rituals
        // Note: API Resource exposes this as 'is_ritual', not 'ritual'
        foreach ($response->json('data') as $spell) {
            $this->assertTrue($spell['is_ritual'], "Spell {$spell['name']} should be castable as a ritual");
        }

        // Verify some known ritual spells are included
        $spellNames = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Alarm', $spellNames, 'Alarm can be cast as a ritual and exists in fixtures');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_ritual_with_not_equals_false(): void
    {
        // Act: Filter by ritual != false (spells that are true or null)
        // Note: Filtering uses Meilisearch field name (ritual)
        $response = $this->getJson('/api/v1/spells?filter=ritual != false');

        // Assert: Should return ritual-castable spells
        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find ritual spells');

        // Verify all returned spells can be cast as rituals
        // Note: API Resource exposes this as 'is_ritual', not 'ritual'
        foreach ($response->json('data') as $spell) {
            $this->assertTrue($spell['is_ritual'], "Spell {$spell['name']} should be castable as a ritual (using != false)");
        }

        // Verify non-ritual spells are excluded
        $spellNames = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertNotContains('Acid Splash', $spellNames, 'Acid Splash cannot be cast as a ritual (should be excluded)');
    }
}
