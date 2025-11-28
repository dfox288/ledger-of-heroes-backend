<?php

namespace Tests\Feature\Api;

use App\Models\CharacterClass;
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
    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_classes_by_is_spellcaster_true(): void
    {
        $spellcasterCount = CharacterClass::whereNotNull('spellcasting_ability_id')->count();
        $this->assertGreaterThan(0, $spellcasterCount, 'Should have spellcaster classes');

        $response = $this->getJson('/api/v1/classes?filter=is_spellcaster = true');

        $response->assertOk();
        $this->assertEquals($spellcasterCount, $response->json('meta.total'));

        // Verify all returned classes are spellcasters
        foreach ($response->json('data') as $class) {
            $classModel = CharacterClass::find($class['id']);
            $this->assertNotNull($classModel->spellcasting_ability_id, "{$class['name']} should be a spellcaster");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_classes_by_is_spellcaster_false(): void
    {
        $nonSpellcasterCount = CharacterClass::whereNull('spellcasting_ability_id')->count();

        $response = $this->getJson('/api/v1/classes?filter=is_spellcaster = false');

        $response->assertOk();
        $this->assertEquals($nonSpellcasterCount, $response->json('meta.total'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_classes_by_hit_die_12(): void
    {
        $d12Count = CharacterClass::where('hit_die', 12)->count();
        $this->assertGreaterThan(0, $d12Count, 'Should have d12 classes (Barbarian)');

        $response = $this->getJson('/api/v1/classes?filter=hit_die = 12');

        $response->assertOk();
        $this->assertEquals($d12Count, $response->json('meta.total'));

        // Barbarian should be in results
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Barbarian', $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_classes_by_hit_die_10(): void
    {
        $d10Count = CharacterClass::where('hit_die', 10)->count();
        $this->assertGreaterThan(0, $d10Count, 'Should have d10 classes (Fighter, Ranger, Paladin)');

        $response = $this->getJson('/api/v1/classes?filter=hit_die = 10');

        $response->assertOk();
        $this->assertEquals($d10Count, $response->json('meta.total'));

        // Fighter should be in results
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Fighter', $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_classes_by_combined_hit_die_and_is_spellcaster(): void
    {
        // d10 spellcasters: Ranger, Paladin
        $d10SpellcasterCount = CharacterClass::where('hit_die', 10)
            ->whereNotNull('spellcasting_ability_id')
            ->count();

        $response = $this->getJson('/api/v1/classes?filter=hit_die = 10 AND is_spellcaster = true');

        $response->assertOk();
        $this->assertEquals($d10SpellcasterCount, $response->json('meta.total'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_classes_by_combined_hit_die_and_is_spellcaster_false(): void
    {
        // d12 non-spellcasters: Barbarian (and subclasses)
        $d12NonSpellcasterCount = CharacterClass::where('hit_die', 12)
            ->whereNull('spellcasting_ability_id')
            ->count();

        $response = $this->getJson('/api/v1/classes?filter=hit_die = 12 AND is_spellcaster = false');

        $response->assertOk();
        $this->assertEquals($d12NonSpellcasterCount, $response->json('meta.total'));
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
        $this->assertEquals($level9Classes, $response->json('meta.total'));

        // Wizard should be in results if it has 9th level spells
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Wizard', $names);
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
        $baseClassCount = CharacterClass::whereNull('parent_class_id')->count();
        $this->assertGreaterThan(0, $baseClassCount, 'Should have base classes');

        $response = $this->getJson('/api/v1/classes?filter=is_base_class = true');

        $response->assertOk();
        $this->assertEquals($baseClassCount, $response->json('meta.total'));

        // Verify all returned classes are base classes
        foreach ($response->json('data') as $class) {
            $classModel = CharacterClass::find($class['id']);
            $this->assertNull($classModel->parent_class_id, "{$class['name']} should be a base class");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_classes_by_is_base_class_false(): void
    {
        $subclassCount = CharacterClass::whereNotNull('parent_class_id')->count();
        $this->assertGreaterThan(0, $subclassCount, 'Should have subclasses');

        $response = $this->getJson('/api/v1/classes?filter=is_base_class = false');

        $response->assertOk();
        $this->assertEquals($subclassCount, $response->json('meta.total'));

        // Verify all returned classes are subclasses
        foreach ($response->json('data') as $class) {
            $classModel = CharacterClass::find($class['id']);
            $this->assertNotNull($classModel->parent_class_id, "{$class['name']} should be a subclass");
        }
    }
}
