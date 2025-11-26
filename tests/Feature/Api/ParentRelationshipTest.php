<?php

namespace Tests\Feature\Api;

use App\Models\CharacterClass;
use App\Models\Race;
use Tests\TestCase;

/**
 * Tests for parent-child relationship rendering in API resources.
 *
 * These tests use pre-imported data from SearchTestExtension.
 * No RefreshDatabase needed - all tests are read-only against shared data.
 */
#[\PHPUnit\Framework\Attributes\Group('feature-search')]
#[\PHPUnit\Framework\Attributes\Group('search-isolated')]
class ParentRelationshipTest extends TestCase
{
    protected $seed = false;

    // ==================== RACE TESTS ====================

    #[\PHPUnit\Framework\Attributes\Test]
    public function race_index_returns_parent_race_with_minimal_data(): void
    {
        // Find a subrace from imported data (e.g., High Elf)
        $subrace = Race::whereNotNull('parent_race_id')->first();
        $this->assertNotNull($subrace, 'Should have subraces in imported data');

        $response = $this->getJson('/api/v1/races');

        $response->assertOk();

        $subraceData = collect($response->json('data'))
            ->firstWhere('slug', $subrace->slug);

        $this->assertNotNull($subraceData, "Subrace {$subrace->slug} not found in response");

        // Should have parent_race with minimal data
        $this->assertArrayHasKey('parent_race', $subraceData);
        $this->assertNotNull($subraceData['parent_race']);
        $this->assertEquals($subrace->parent_race_id, $subraceData['parent_race']['id']);
        $this->assertArrayHasKey('slug', $subraceData['parent_race']);
        $this->assertArrayHasKey('name', $subraceData['parent_race']);

        // Should NOT have parent's relationships in index (they're not loaded)
        $this->assertArrayNotHasKey('traits', $subraceData['parent_race']);
        $this->assertArrayNotHasKey('modifiers', $subraceData['parent_race']);
        $this->assertArrayNotHasKey('subraces', $subraceData['parent_race']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function race_show_returns_parent_race_with_full_relationships(): void
    {
        // Find a subrace from imported data that has a parent with traits
        $subrace = Race::whereNotNull('parent_race_id')
            ->whereHas('parent.traits')
            ->first();

        if (! $subrace) {
            // If no subrace with parent traits, just test the structure
            $subrace = Race::whereNotNull('parent_race_id')->first();
        }

        $this->assertNotNull($subrace, 'Should have subraces in imported data');

        $response = $this->getJson("/api/v1/races/{$subrace->slug}");

        $response->assertOk();

        $data = $response->json('data');

        // Should have parent_race
        $this->assertArrayHasKey('parent_race', $data);
        $this->assertNotNull($data['parent_race']);
        $this->assertEquals($subrace->parent_race_id, $data['parent_race']['id']);
        $this->assertArrayHasKey('slug', $data['parent_race']);
        $this->assertArrayHasKey('name', $data['parent_race']);

        // Should HAVE parent's relationships in show endpoint
        $this->assertArrayHasKey('traits', $data['parent_race']);
        $this->assertIsArray($data['parent_race']['traits']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function race_search_returns_parent_race(): void
    {
        // Find a subrace from imported data
        $subrace = Race::whereNotNull('parent_race_id')->first();
        $this->assertNotNull($subrace, 'Should have subraces in imported data');

        // Search for the subrace
        $searchTerm = substr($subrace->name, 0, 4); // e.g., "High" from "High Elf"
        $response = $this->getJson("/api/v1/races?q={$searchTerm}");

        $response->assertOk();

        $subraceData = collect($response->json('data'))
            ->firstWhere('slug', $subrace->slug);

        if ($subraceData) {
            // Should have parent_race with minimal data
            $this->assertArrayHasKey('parent_race', $subraceData);
            $this->assertNotNull($subraceData['parent_race']);
            $this->assertEquals($subrace->parent_race_id, $subraceData['parent_race']['id']);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function race_index_with_base_race_has_null_parent(): void
    {
        // Find a base race from imported data (has no parent_race_id)
        $baseRace = Race::whereNull('parent_race_id')->first();
        $this->assertNotNull($baseRace, 'Should have base races in imported data');

        $response = $this->getJson('/api/v1/races');

        $response->assertOk();

        $baseRaceData = collect($response->json('data'))
            ->firstWhere('slug', $baseRace->slug);

        $this->assertNotNull($baseRaceData);
        // Base races should not have parent_race key or it should be null
        $this->assertTrue(
            ! isset($baseRaceData['parent_race']) || $baseRaceData['parent_race'] === null,
            'Base race should not have parent_race or it should be null'
        );
    }

    // ==================== CLASS TESTS ====================

    #[\PHPUnit\Framework\Attributes\Test]
    public function class_index_returns_parent_class_with_minimal_data(): void
    {
        // Find a subclass from imported data
        $subclass = CharacterClass::whereNotNull('parent_class_id')->first();
        $this->assertNotNull($subclass, 'Should have subclasses in imported data');

        $response = $this->getJson('/api/v1/classes');

        $response->assertOk();

        $subclassData = collect($response->json('data'))
            ->firstWhere('slug', $subclass->slug);

        $this->assertNotNull($subclassData, "Subclass {$subclass->slug} not found in response");

        // Should have parent_class with minimal data
        $this->assertArrayHasKey('parent_class', $subclassData);
        $this->assertNotNull($subclassData['parent_class']);
        $this->assertEquals($subclass->parent_class_id, $subclassData['parent_class']['id']);
        $this->assertArrayHasKey('slug', $subclassData['parent_class']);
        $this->assertArrayHasKey('name', $subclassData['parent_class']);

        // Should NOT have parent's relationships in index
        $this->assertArrayNotHasKey('features', $subclassData['parent_class']);
        $this->assertArrayNotHasKey('subclasses', $subclassData['parent_class']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function class_show_returns_parent_class_with_full_relationships(): void
    {
        // Find a subclass from imported data that has a parent with features
        $subclass = CharacterClass::whereNotNull('parent_class_id')
            ->whereHas('parentClass.features')
            ->first();

        if (! $subclass) {
            // If no subclass with parent features, just test the structure
            $subclass = CharacterClass::whereNotNull('parent_class_id')->first();
        }

        $this->assertNotNull($subclass, 'Should have subclasses in imported data');

        $response = $this->getJson("/api/v1/classes/{$subclass->slug}");

        $response->assertOk();

        $data = $response->json('data');

        // Should have parent_class
        $this->assertArrayHasKey('parent_class', $data);
        $this->assertNotNull($data['parent_class']);
        $this->assertEquals($subclass->parent_class_id, $data['parent_class']['id']);
        $this->assertArrayHasKey('slug', $data['parent_class']);
        $this->assertArrayHasKey('name', $data['parent_class']);

        // Should HAVE parent's relationships in show endpoint
        $this->assertArrayHasKey('features', $data['parent_class']);
        $this->assertIsArray($data['parent_class']['features']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function class_search_returns_parent_class(): void
    {
        // Find a subclass from imported data
        $subclass = CharacterClass::whereNotNull('parent_class_id')->first();
        $this->assertNotNull($subclass, 'Should have subclasses in imported data');

        // Search for the subclass (e.g., "evocation" for School of Evocation)
        $searchTerm = explode(' ', $subclass->name);
        $lastWord = end($searchTerm);
        $response = $this->getJson("/api/v1/classes?q={$lastWord}");

        $response->assertOk();

        $subclassData = collect($response->json('data'))
            ->firstWhere('slug', $subclass->slug);

        if ($subclassData) {
            // Should have parent_class with minimal data
            $this->assertArrayHasKey('parent_class', $subclassData);
            $this->assertNotNull($subclassData['parent_class']);
            $this->assertEquals($subclass->parent_class_id, $subclassData['parent_class']['id']);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function class_index_with_base_class_has_null_parent(): void
    {
        // Find a base class from imported data (has no parent_class_id)
        $baseClass = CharacterClass::whereNull('parent_class_id')->first();
        $this->assertNotNull($baseClass, 'Should have base classes in imported data');

        $response = $this->getJson('/api/v1/classes');

        $response->assertOk();

        $baseClassData = collect($response->json('data'))
            ->firstWhere('slug', $baseClass->slug);

        $this->assertNotNull($baseClassData);
        // Base classes should not have parent_class key or it should be null
        $this->assertTrue(
            ! isset($baseClassData['parent_class']) || $baseClassData['parent_class'] === null,
            'Base class should not have parent_class or it should be null'
        );
    }
}
