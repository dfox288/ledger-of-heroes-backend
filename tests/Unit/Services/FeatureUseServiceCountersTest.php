<?php

namespace Tests\Unit\Services;

use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\ClassCounter;
use App\Models\ClassFeature;
use App\Services\FeatureUseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureUseServiceCountersTest extends TestCase
{
    use RefreshDatabase;

    private FeatureUseService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FeatureUseService::class);
    }

    // =====================
    // getCountersForCharacter() Tests
    // =====================

    public function test_returns_empty_collection_for_character_without_counters(): void
    {
        $class = CharacterClass::factory()->create(['name' => 'Commoner']);
        $character = Character::factory()
            ->withClass($class)
            ->level(1)
            ->withFeatures()
            ->create();

        $counters = $this->service->getCountersForCharacter($character);

        $this->assertCount(0, $counters);
    }

    public function test_returns_rage_counter_for_barbarian(): void
    {
        // Create Barbarian class with Rage feature and counter
        $barbarian = CharacterClass::factory()->create([
            'name' => 'Barbarian',
            'slug' => 'test:barbarian',
        ]);

        ClassFeature::factory()->create([
            'class_id' => $barbarian->id,
            'feature_name' => 'Rage',
            'level' => 1,
            'resets_on' => 'long_rest',
        ]);

        ClassCounter::create([
            'class_id' => $barbarian->id,
            'level' => 1,
            'counter_name' => 'Rage',
            'counter_value' => 2,
            'reset_timing' => 'L',
        ]);

        $character = Character::factory()
            ->withClass($barbarian)
            ->level(1)
            ->withFeatures()
            ->create();

        $counters = $this->service->getCountersForCharacter($character);

        $this->assertCount(1, $counters);

        $rage = $counters->first();
        $this->assertEquals('Rage', $rage['name']);
        $this->assertEquals(2, $rage['max']);
        $this->assertEquals(2, $rage['current']);
        $this->assertEquals('long_rest', $rage['reset_on']);
        $this->assertFalse($rage['unlimited']);
    }

    public function test_counter_max_scales_with_class_level(): void
    {
        $barbarian = CharacterClass::factory()->create([
            'name' => 'Barbarian',
            'slug' => 'test:barbarian-scaling',
        ]);

        ClassFeature::factory()->create([
            'class_id' => $barbarian->id,
            'feature_name' => 'Rage',
            'level' => 1,
            'resets_on' => 'long_rest',
        ]);

        // Add level-based counter progression
        ClassCounter::create([
            'class_id' => $barbarian->id,
            'level' => 1,
            'counter_name' => 'Rage',
            'counter_value' => 2,
            'reset_timing' => 'L',
        ]);
        ClassCounter::create([
            'class_id' => $barbarian->id,
            'level' => 3,
            'counter_name' => 'Rage',
            'counter_value' => 3,
            'reset_timing' => 'L',
        ]);
        ClassCounter::create([
            'class_id' => $barbarian->id,
            'level' => 6,
            'counter_name' => 'Rage',
            'counter_value' => 4,
            'reset_timing' => 'L',
        ]);

        // Test at level 5 - should use level 3 counter (value = 3)
        $character = Character::factory()
            ->withClass($barbarian)
            ->level(5)
            ->withFeatures()
            ->create();

        $counters = $this->service->getCountersForCharacter($character);
        $rage = $counters->first();

        $this->assertEquals(3, $rage['max']);
    }

    public function test_marks_unlimited_counters_correctly(): void
    {
        $barbarian = CharacterClass::factory()->create([
            'name' => 'Barbarian',
            'slug' => 'test:barbarian-unlimited',
        ]);

        ClassFeature::factory()->create([
            'class_id' => $barbarian->id,
            'feature_name' => 'Rage',
            'level' => 1,
            'resets_on' => 'long_rest',
        ]);

        // Level 20 Rage is unlimited (-1)
        ClassCounter::create([
            'class_id' => $barbarian->id,
            'level' => 1,
            'counter_name' => 'Rage',
            'counter_value' => -1,
            'reset_timing' => 'L',
        ]);

        $character = Character::factory()
            ->withClass($barbarian)
            ->level(20)
            ->withFeatures()
            ->create();

        $counters = $this->service->getCountersForCharacter($character);
        $rage = $counters->first();

        $this->assertTrue($rage['unlimited']);
        $this->assertEquals(-1, $rage['max']);
    }

    public function test_generates_correct_slug_format(): void
    {
        $barbarian = CharacterClass::factory()->create([
            'name' => 'Barbarian',
            'slug' => 'phb:barbarian',
        ]);

        ClassFeature::factory()->create([
            'class_id' => $barbarian->id,
            'feature_name' => 'Rage',
            'level' => 1,
            'resets_on' => 'long_rest',
        ]);

        ClassCounter::create([
            'class_id' => $barbarian->id,
            'level' => 1,
            'counter_name' => 'Rage',
            'counter_value' => 2,
            'reset_timing' => 'L',
        ]);

        $character = Character::factory()
            ->withClass($barbarian)
            ->level(1)
            ->withFeatures()
            ->create();

        $counters = $this->service->getCountersForCharacter($character);
        $rage = $counters->first();

        // Slug format: {source}:{class-slug}:{counter-name-slug}
        $this->assertEquals('phb:barbarian:rage', $rage['slug']);
    }

    public function test_returns_counters_from_multiple_classes(): void
    {
        // Create Barbarian with Rage
        $barbarian = CharacterClass::factory()->create([
            'name' => 'Barbarian',
            'slug' => 'test:barbarian-multi',
        ]);
        ClassFeature::factory()->create([
            'class_id' => $barbarian->id,
            'feature_name' => 'Rage',
            'level' => 1,
            'resets_on' => 'long_rest',
        ]);
        ClassCounter::create([
            'class_id' => $barbarian->id,
            'level' => 1,
            'counter_name' => 'Rage',
            'counter_value' => 2,
            'reset_timing' => 'L',
        ]);

        // Create Fighter with Action Surge
        $fighter = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'test:fighter-multi',
        ]);
        ClassFeature::factory()->create([
            'class_id' => $fighter->id,
            'feature_name' => 'Action Surge',
            'level' => 2,
            'resets_on' => 'short_rest',
        ]);
        ClassCounter::create([
            'class_id' => $fighter->id,
            'level' => 2,
            'counter_name' => 'Action Surge',
            'counter_value' => 1,
            'reset_timing' => 'S',
        ]);

        // Create multiclass character: Barbarian 3 / Fighter 2
        $character = Character::factory()
            ->withClass($barbarian, 3)
            ->withClass($fighter, 2)
            ->withFeatures()
            ->create();

        $counters = $this->service->getCountersForCharacter($character);

        $this->assertCount(2, $counters);

        $counterNames = $counters->pluck('name')->toArray();
        $this->assertContains('Rage', $counterNames);
        $this->assertContains('Action Surge', $counterNames);
    }

    public function test_includes_subclass_counters(): void
    {
        // Create base class
        $barbarian = CharacterClass::factory()->create([
            'name' => 'Barbarian',
            'slug' => 'test:barbarian-sub',
        ]);

        // Create subclass with counter
        $zealot = CharacterClass::factory()->create([
            'name' => 'Path of the Zealot',
            'slug' => 'test:path-of-the-zealot',
            'parent_class_id' => $barbarian->id,
        ]);

        ClassFeature::factory()->create([
            'class_id' => $zealot->id,
            'feature_name' => 'Zealous Presence',
            'level' => 10,
            'resets_on' => 'long_rest',
        ]);

        ClassCounter::create([
            'class_id' => $zealot->id,
            'level' => 10,
            'counter_name' => 'Zealous Presence',
            'counter_value' => 1,
            'reset_timing' => 'L',
        ]);

        $character = Character::factory()
            ->withClass($barbarian)
            ->level(10)
            ->withFeatures()
            ->create();

        // Manually add subclass feature (normally done via choice system)
        $subclassFeature = ClassFeature::where('class_id', $zealot->id)
            ->where('feature_name', 'Zealous Presence')
            ->first();

        $character->features()->create([
            'feature_type' => ClassFeature::class,
            'feature_id' => $subclassFeature->id,
            'source' => 'subclass',
            'level_acquired' => 10,
            'max_uses' => 1,
            'uses_remaining' => 1,
        ]);

        $counters = $this->service->getCountersForCharacter($character);

        $zealousPresence = $counters->firstWhere('name', 'Zealous Presence');
        $this->assertNotNull($zealousPresence);
        $this->assertEquals('subclass', $zealousPresence['source_type']);
    }

    public function test_includes_source_information(): void
    {
        $barbarian = CharacterClass::factory()->create([
            'name' => 'Barbarian',
            'slug' => 'test:barbarian-source',
        ]);

        ClassFeature::factory()->create([
            'class_id' => $barbarian->id,
            'feature_name' => 'Rage',
            'level' => 1,
            'resets_on' => 'long_rest',
        ]);

        ClassCounter::create([
            'class_id' => $barbarian->id,
            'level' => 1,
            'counter_name' => 'Rage',
            'counter_value' => 2,
            'reset_timing' => 'L',
        ]);

        $character = Character::factory()
            ->withClass($barbarian)
            ->level(1)
            ->withFeatures()
            ->create();

        $counters = $this->service->getCountersForCharacter($character);
        $rage = $counters->first();

        $this->assertEquals('Barbarian', $rage['source']);
        $this->assertEquals('class', $rage['source_type']);
    }

    public function test_counter_response_has_required_fields(): void
    {
        $barbarian = CharacterClass::factory()->create([
            'name' => 'Barbarian',
            'slug' => 'test:barbarian-fields',
        ]);

        ClassFeature::factory()->create([
            'class_id' => $barbarian->id,
            'feature_name' => 'Rage',
            'level' => 1,
            'resets_on' => 'long_rest',
        ]);

        ClassCounter::create([
            'class_id' => $barbarian->id,
            'level' => 1,
            'counter_name' => 'Rage',
            'counter_value' => 2,
            'reset_timing' => 'L',
        ]);

        $character = Character::factory()
            ->withClass($barbarian)
            ->level(1)
            ->withFeatures()
            ->create();

        $counters = $this->service->getCountersForCharacter($character);
        $rage = $counters->first();

        // Verify all required fields from API contract
        $this->assertArrayHasKey('id', $rage);
        $this->assertArrayHasKey('slug', $rage);
        $this->assertArrayHasKey('name', $rage);
        $this->assertArrayHasKey('current', $rage);
        $this->assertArrayHasKey('max', $rage);
        $this->assertArrayHasKey('reset_on', $rage);
        $this->assertArrayHasKey('source', $rage);
        $this->assertArrayHasKey('source_type', $rage);
        $this->assertArrayHasKey('unlimited', $rage);
    }
}
