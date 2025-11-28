<?php

namespace Tests\Feature\Api;

use App\Models\CharacterClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for CharacterClass-specific filter operators using Meilisearch.
 *
 * These tests use factory-based data and are self-contained.
 */
#[\PHPUnit\Framework\Attributes\Group('feature-search')]
#[\PHPUnit\Framework\Attributes\Group('search-isolated')]
class ClassEntitySpecificFiltersApiTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = \Database\Seeders\TestDatabaseSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_classes_by_is_spellcaster_true(): void
    {
        $response = $this->getJson('/api/v1/classes?filter=is_spellcaster = true');

        $response->assertOk();

        // Verify all returned classes are spellcasters
        foreach ($response->json('data') as $class) {
            $classModel = CharacterClass::find($class['id']);
            $this->assertNotNull($classModel->spellcasting_ability_id, "{$class['name']} should be a spellcaster");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_classes_by_is_spellcaster_false(): void
    {
        $response = $this->getJson('/api/v1/classes?filter=is_spellcaster = false');

        $response->assertOk();

        // Verify all returned classes are non-spellcasters
        foreach ($response->json('data') as $class) {
            $classModel = CharacterClass::find($class['id']);
            $this->assertNull($classModel->spellcasting_ability_id, "{$class['name']} should not be a spellcaster");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_classes_by_hit_die_12(): void
    {
        $response = $this->getJson('/api/v1/classes?filter=hit_die = 12');

        $response->assertOk();

        // Verify all returned classes have hit_die = 12
        foreach ($response->json('data') as $class) {
            $classModel = CharacterClass::find($class['id']);
            $this->assertEquals(12, $classModel->hit_die, "{$class['name']} should have hit_die = 12");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_classes_by_hit_die_10(): void
    {
        $response = $this->getJson('/api/v1/classes?filter=hit_die = 10');

        $response->assertOk();

        // Verify all returned classes have hit_die = 10
        foreach ($response->json('data') as $class) {
            $classModel = CharacterClass::find($class['id']);
            $this->assertEquals(10, $classModel->hit_die, "{$class['name']} should have hit_die = 10");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_classes_by_combined_hit_die_and_is_spellcaster(): void
    {
        // d10 spellcasters: Ranger, Paladin
        $response = $this->getJson('/api/v1/classes?filter=hit_die = 10 AND is_spellcaster = true');

        $response->assertOk();

        // Verify all returned classes have hit_die = 10 AND are spellcasters
        foreach ($response->json('data') as $class) {
            $classModel = CharacterClass::find($class['id']);
            $this->assertEquals(10, $classModel->hit_die, "{$class['name']} should have hit_die = 10");
            $this->assertNotNull($classModel->spellcasting_ability_id, "{$class['name']} should be a spellcaster");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_classes_by_combined_hit_die_and_is_spellcaster_false(): void
    {
        // d12 non-spellcasters: Barbarian (and subclasses)
        $response = $this->getJson('/api/v1/classes?filter=hit_die = 12 AND is_spellcaster = false');

        $response->assertOk();

        // Verify all returned classes have hit_die = 12 AND are not spellcasters
        foreach ($response->json('data') as $class) {
            $classModel = CharacterClass::find($class['id']);
            $this->assertEquals(12, $classModel->hit_die, "{$class['name']} should have hit_die = 12");
            $this->assertNull($classModel->spellcasting_ability_id, "{$class['name']} should not be a spellcaster");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_classes_by_max_spell_level(): void
    {
        // Note: Class-spell relationships are created during spell imports, not class imports.
        // This test verifies the max_spell_level filter works when relationships exist.

        // Get classes with 9th level spells from the seeded data
        $level9Classes = CharacterClass::whereHas('spells', function ($q) {
            $q->where('level', 9);
        })->count();

        // Skip if no spell relationships exist (class-only import)
        if ($level9Classes === 0) {
            $this->markTestSkipped('No class-spell relationships in test data. Run spell imports first.');
        }

        $response = $this->getJson('/api/v1/classes?filter=max_spell_level = 9');

        $response->assertOk();

        // Verify all returned classes have 9th level spells
        foreach ($response->json('data') as $class) {
            $classModel = CharacterClass::find($class['id']);
            $has9thLevelSpells = $classModel->spells()->where('level', 9)->exists();
            $this->assertTrue($has9thLevelSpells, "{$class['name']} should have 9th level spells");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_rejects_invalid_filter_syntax(): void
    {
        $response = $this->getJson('/api/v1/classes?filter=invalid_field INVALID_OPERATOR value');

        $this->assertContains($response->status(), [400, 422, 500]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_accepts_valid_filter_with_multiple_conditions(): void
    {
        $response = $this->getJson('/api/v1/classes?filter=hit_die = 6 AND is_spellcaster = true');

        $response->assertOk();
        $response->assertJsonStructure(['data', 'links', 'meta']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_classes_by_is_base_class_true(): void
    {
        $response = $this->getJson('/api/v1/classes?filter=is_base_class = true');

        $response->assertOk();

        // Verify all returned classes are base classes
        foreach ($response->json('data') as $class) {
            $classModel = CharacterClass::find($class['id']);
            $this->assertNull($classModel->parent_class_id, "{$class['name']} should be a base class");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_classes_by_is_base_class_false(): void
    {
        $response = $this->getJson('/api/v1/classes?filter=is_base_class = false');

        $response->assertOk();

        // Verify all returned classes are subclasses
        foreach ($response->json('data') as $class) {
            $classModel = CharacterClass::find($class['id']);
            $this->assertNotNull($classModel->parent_class_id, "{$class['name']} should be a subclass");
        }
    }
}
