<?php

namespace Tests\Feature\Api;

use App\Models\CharacterClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WaitsForMeilisearch;
use Tests\Feature\Api\Concerns\TestsFilterOperators;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-search')]
class ClassFilterOperatorTest extends TestCase
{
    use RefreshDatabase;
    use TestsFilterOperators;
    use WaitsForMeilisearch;

    protected $seeder = \Database\Seeders\TestDatabaseSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure Meilisearch indexes for testing
        $this->artisan('search:configure-indexes');
    }

    // ============================================================
    // Entity-Specific Configuration
    // ============================================================

    protected function getEndpoint(): string
    {
        return '/api/v1/classes';
    }

    protected function getIntegerFieldConfig(): ?array
    {
        return [
            'field' => 'hit_die',
            'testValue' => 8,
            'lowValue' => 8,
            'highValue' => 10,
        ];
    }

    protected function getStringFieldConfig(): ?array
    {
        return [
            'field' => 'spellcasting_ability',
            'testValue' => 'INT',
            'excludeValue' => 'INT',
        ];
    }

    protected function getBooleanFieldConfig(): ?array
    {
        return [
            'field' => 'is_base_class',
            'verifyCallback' => function (TestCase $test, array $class, bool $expectedValue) {
                $test->assertEquals($expectedValue, $class['is_base_class'], "Class {$class['name']} should have is_base_class = ".(string) $expectedValue);

                if ($expectedValue) {
                    $test->assertArrayNotHasKey('parent_class', $class, "Base class {$class['name']} should have no parent");
                } else {
                    $test->assertArrayHasKey('parent_class', $class, "Subclass {$class['name']} should have a parent");
                }
            },
        ];
    }

    protected function getArrayFieldConfig(): ?array
    {
        return [
            'field' => 'source_codes',
            'testValues' => ['PHB', 'XGTE'],
            'excludeValue' => 'XGTE',
            'verifyCallback' => function (TestCase $test, array $class, array $expectedValues, bool $shouldContain) {
                $sources = $class['sources'] ?? [];
                $sourceCodes = collect($sources)->pluck('code')->toArray();

                if ($shouldContain) {
                    $hasExpected = ! empty(array_intersect($expectedValues, $sourceCodes));
                    $test->assertTrue($hasExpected, "Class {$class['name']} should have one of: ".implode(', ', $expectedValues));
                } else {
                    foreach ($expectedValues as $code) {
                        // For NOT IN, verify source isn't the ONLY source
                        if (count($sourceCodes) === 1) {
                            $test->assertNotContains($code, $sourceCodes, "Class {$class['name']} should not have only source {$code}");
                        }
                    }
                }
            },
        ];
    }

    // ============================================================
    // Integer Operators (hit_die field) - 7 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_hit_die_with_equals(): void
    {
        $this->assertIntegerEquals();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_hit_die_with_not_equals(): void
    {
        $this->assertIntegerNotEquals();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_hit_die_with_greater_than(): void
    {
        $this->assertIntegerGreaterThan();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_hit_die_with_greater_than_or_equal(): void
    {
        $this->assertIntegerGreaterThanOrEqual();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_hit_die_with_less_than(): void
    {
        $this->assertIntegerLessThan();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_hit_die_with_less_than_or_equal(): void
    {
        $this->assertIntegerLessThanOrEqual();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_hit_die_with_to_range(): void
    {
        $this->assertIntegerToRange();
    }

    // ============================================================
    // String Operators (spellcasting_ability field) - 2 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_spellcasting_ability_with_equals(): void
    {
        // Assert: Database should have imported classes
        $this->assertGreaterThan(0, CharacterClass::count(), 'Database must be seeded');

        // Act: Filter by spellcasting_ability = INT (Intelligence casters like Wizard, Artificer)
        $response = $this->getJson('/api/v1/classes?filter=spellcasting_ability = INT&per_page=100');

        // Assert
        $response->assertOk();
        $response->assertJsonStructure(['data' => [['spellcasting_ability']]]);
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find Intelligence casters');

        // Verify all returned classes have spellcasting_ability.code = 'INT'
        foreach ($response->json('data') as $class) {
            $this->assertEquals('INT', $class['spellcasting_ability']['code'], "Class {$class['name']} should be Intelligence caster");
        }

        // Verify some known Intelligence casters are included
        $classNames = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Wizard', $classNames, 'Wizard is an Intelligence caster');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_spellcasting_ability_with_not_equals(): void
    {
        // Assert: Database should have imported classes
        $this->assertGreaterThan(0, CharacterClass::count(), 'Database must be seeded');

        // Act: Filter by spellcasting_ability != INT (all casters except Intelligence)
        $response = $this->getJson('/api/v1/classes?filter=spellcasting_ability != INT');

        // Assert: Should return spellcasters with other abilities (WIS, CHA)
        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find non-Intelligence casters');

        // Verify no Intelligence casters in results
        foreach ($response->json('data') as $class) {
            // Only check classes that have spellcasting ability
            if (isset($class['spellcasting_ability'])) {
                $this->assertNotEquals('INT', $class['spellcasting_ability']['code'], "Class {$class['name']} should not be Intelligence caster");
            }
        }

        // Verify we have casters from other abilities
        $abilities = collect($response->json('data'))
            ->filter(fn ($class) => isset($class['spellcasting_ability']))
            ->pluck('spellcasting_ability.code')
            ->unique()
            ->toArray();
        $this->assertNotContains('INT', $abilities, 'Intelligence should be excluded');
    }

    // ============================================================
    // Boolean Operators (is_base_class field) - 7 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_is_base_class_with_equals_true(): void
    {
        $this->assertBooleanEqualsTrue();

        // Additional entity-specific assertions
        $response = $this->getJson('/api/v1/classes?filter=is_base_class = true&per_page=100');
        $classNames = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Wizard', $classNames, 'Wizard is a base class');
        $this->assertContains('Fighter', $classNames, 'Fighter is a base class');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_is_base_class_with_equals_false(): void
    {
        $this->assertBooleanEqualsFalse();

        // Additional entity-specific assertions
        $response = $this->getJson('/api/v1/classes?filter=is_base_class = false&per_page=100');
        $parentClasses = collect($response->json('data'))
            ->pluck('parent_class.name')
            ->unique()
            ->toArray();
        $this->assertGreaterThan(0, count($parentClasses), 'Should have subclasses with parent classes');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_is_base_class_with_not_equals_true(): void
    {
        $this->assertBooleanNotEqualsTrue();

        // Additional entity-specific assertions
        $response = $this->getJson('/api/v1/classes?filter=is_base_class != true&per_page=100');
        $classNames = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertNotContains('Wizard', $classNames, 'Wizard is a base class (should be excluded)');
        $this->assertNotContains('Fighter', $classNames, 'Fighter is a base class (should be excluded)');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_is_base_class_with_not_equals_false(): void
    {
        $this->assertBooleanNotEqualsFalse();

        // Additional entity-specific assertions
        $response = $this->getJson('/api/v1/classes?filter=is_base_class != false&per_page=100');
        $classNames = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Wizard', $classNames, 'Wizard is a base class');
        $this->assertContains('Fighter', $classNames, 'Fighter is a base class');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_is_base_class_with_is_null(): void
    {
        $this->assertBooleanIsNull();

        // Additional entity-specific assertion - with clean data, expect 0 results
        $response = $this->getJson('/api/v1/classes?filter=is_base_class IS NULL');
        $this->assertEquals(0, $response->json('meta.total'), 'IS NULL should return 0 results for properly imported data');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_is_base_class_with_is_not_null(): void
    {
        $this->assertBooleanIsNotNull();

        // Additional entity-specific assertion
        $response = $this->getJson('/api/v1/classes?filter=is_base_class IS NOT NULL');
        $this->assertGreaterThanOrEqual(CharacterClass::count(), $response->json('meta.total'), 'Should return all classes or more if index has stale data');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_is_base_class_with_not_equals(): void
    {
        // Assert: Database should have imported classes
        $this->assertGreaterThan(0, CharacterClass::count(), 'Database must be seeded');

        // Act: Filter by is_base_class != true (should return subclasses)
        $response = $this->getJson('/api/v1/classes?filter=is_base_class != true&per_page=100');

        // Assert: Should return subclasses only
        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), 'Should find subclasses');

        // Verify all returned classes are NOT base classes
        foreach ($response->json('data') as $class) {
            $this->assertFalse($class['is_base_class'], "Class {$class['name']} should not be a base class");
        }
    }

    // ============================================================
    // Array Operators (source_codes field) - 3 tests
    // ============================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_source_codes_with_in(): void
    {
        $this->assertArrayIn();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_source_codes_with_not_in(): void
    {
        $this->assertArrayNotIn();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_source_codes_with_is_empty(): void
    {
        $this->assertArrayIsEmpty();
    }
}
