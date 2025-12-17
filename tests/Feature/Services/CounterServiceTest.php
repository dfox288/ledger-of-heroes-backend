<?php

namespace Tests\Feature\Services;

use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use App\Models\CharacterCounter;
use App\Models\EntityCounter;
use App\Services\CounterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CounterServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CounterService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CounterService::class);
    }

    #[Test]
    public function it_syncs_counters_for_barbarian_at_level_1(): void
    {
        // Create a barbarian class with Rage counter definition
        $barbarian = CharacterClass::factory()->create(['slug' => 'test:barbarian', 'name' => 'Barbarian']);

        // Define Rage counter at level 1 (2 uses)
        EntityCounter::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $barbarian->id,
            'level' => 1,
            'counter_name' => 'Rage',
            'counter_value' => 2,
            'reset_timing' => 'L',
        ]);

        // Create character with barbarian class at level 1
        $character = Character::factory()->create();
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $barbarian->slug,
            'level' => 1,
        ]);

        // Sync counters
        $this->service->syncCountersForCharacter($character);

        // Verify counter was created
        $counter = CharacterCounter::where('character_id', $character->id)->first();
        $this->assertNotNull($counter);
        $this->assertEquals('class', $counter->source_type);
        $this->assertEquals($barbarian->slug, $counter->source_slug);
        $this->assertEquals('Rage', $counter->counter_name);
        $this->assertEquals(2, $counter->max_uses);
        $this->assertEquals('L', $counter->reset_timing);
        $this->assertNull($counter->current_uses); // Full
    }

    #[Test]
    public function it_updates_max_uses_on_level_up(): void
    {
        $barbarian = CharacterClass::factory()->create(['slug' => 'test:barbarian', 'name' => 'Barbarian']);

        // Rage at level 1: 2 uses
        EntityCounter::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $barbarian->id,
            'level' => 1,
            'counter_name' => 'Rage',
            'counter_value' => 2,
            'reset_timing' => 'L',
        ]);

        // Rage at level 3: 3 uses
        EntityCounter::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $barbarian->id,
            'level' => 3,
            'counter_name' => 'Rage',
            'counter_value' => 3,
            'reset_timing' => 'L',
        ]);

        $character = Character::factory()->create();
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $barbarian->slug,
            'level' => 1,
        ]);

        // Initial sync at level 1
        $this->service->syncCountersForCharacter($character);
        $this->assertEquals(2, CharacterCounter::where('character_id', $character->id)->first()->max_uses);

        // Level up to 3
        CharacterClassPivot::where('character_id', $character->id)->update(['level' => 3]);
        $character->refresh();

        // Re-sync
        $this->service->syncCountersForCharacter($character);
        $this->assertEquals(3, CharacterCounter::where('character_id', $character->id)->first()->max_uses);
    }

    #[Test]
    public function it_creates_separate_counters_for_multiclass(): void
    {
        $fighter = CharacterClass::factory()->create(['slug' => 'test:fighter-psi', 'name' => 'Fighter']);
        $rogue = CharacterClass::factory()->create(['slug' => 'test:rogue-soul', 'name' => 'Rogue']);

        // Both have "Psionic Energy" counter (from Psi Warrior and Soulknife)
        EntityCounter::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $fighter->id,
            'level' => 3,
            'counter_name' => 'Psionic Energy',
            'counter_value' => 6,
            'reset_timing' => 'L',
        ]);
        EntityCounter::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $rogue->id,
            'level' => 3,
            'counter_name' => 'Psionic Energy',
            'counter_value' => 4,
            'reset_timing' => 'L',
        ]);

        $character = Character::factory()->create();
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $fighter->slug,
            'level' => 6,
        ]);
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $rogue->slug,
            'level' => 4,
        ]);

        $this->service->syncCountersForCharacter($character);

        // Should have 2 separate counters
        $counters = CharacterCounter::where('character_id', $character->id)->get();
        $this->assertCount(2, $counters);

        $fighterCounter = $counters->firstWhere('source_slug', $fighter->slug);
        $rogueCounter = $counters->firstWhere('source_slug', $rogue->slug);

        $this->assertEquals(6, $fighterCounter->max_uses);
        $this->assertEquals(4, $rogueCounter->max_uses);
    }

    #[Test]
    public function it_uses_a_counter(): void
    {
        $character = Character::factory()->create();
        $counter = CharacterCounter::factory()->create([
            'character_id' => $character->id,
            'current_uses' => null, // Full
            'max_uses' => 3,
        ]);

        $result = $this->service->useCounter($character, $counter->id);

        $this->assertTrue($result);
        $this->assertEquals(2, $counter->fresh()->current_uses);
    }

    #[Test]
    public function it_resets_counters_by_timing(): void
    {
        $character = Character::factory()->create();

        // Short rest counter - used up
        CharacterCounter::factory()->create([
            'character_id' => $character->id,
            'counter_name' => 'Short Rest Ability',
            'current_uses' => 0,
            'max_uses' => 2,
            'reset_timing' => 'S',
        ]);

        // Long rest counter - used up
        CharacterCounter::factory()->create([
            'character_id' => $character->id,
            'counter_name' => 'Long Rest Ability',
            'current_uses' => 0,
            'max_uses' => 3,
            'reset_timing' => 'L',
        ]);

        // Reset only short rest counters
        $resetCount = $this->service->resetByTiming($character, 'S');

        $this->assertEquals(1, $resetCount);

        $counters = CharacterCounter::where('character_id', $character->id)->get();
        $shortRest = $counters->firstWhere('reset_timing', 'S');
        $longRest = $counters->firstWhere('reset_timing', 'L');

        $this->assertNull($shortRest->current_uses); // Reset to full
        $this->assertEquals(0, $longRest->current_uses); // Still empty
    }

    #[Test]
    public function it_gets_counters_for_api_response(): void
    {
        $character = Character::factory()->create();

        CharacterCounter::factory()->create([
            'character_id' => $character->id,
            'source_type' => 'class',
            'source_slug' => 'phb:barbarian',
            'counter_name' => 'Rage',
            'current_uses' => 1,
            'max_uses' => 3,
            'reset_timing' => 'L',
        ]);

        $counters = $this->service->getCountersForCharacter($character);

        $this->assertCount(1, $counters);
        $counter = $counters->first();

        $this->assertArrayHasKey('id', $counter);
        $this->assertArrayHasKey('name', $counter);
        $this->assertArrayHasKey('current', $counter);
        $this->assertArrayHasKey('max', $counter);
        $this->assertArrayHasKey('reset_on', $counter);
        $this->assertArrayHasKey('source_type', $counter);
        $this->assertArrayHasKey('unlimited', $counter);

        $this->assertEquals('Rage', $counter['name']);
        $this->assertEquals(1, $counter['current']);
        $this->assertEquals(3, $counter['max']);
        $this->assertEquals('long_rest', $counter['reset_on']);
        $this->assertFalse($counter['unlimited']);
    }

    #[Test]
    public function it_includes_slug_in_counter_response(): void
    {
        $character = Character::factory()->create();

        CharacterCounter::factory()->create([
            'character_id' => $character->id,
            'source_type' => 'class',
            'source_slug' => 'phb:barbarian',
            'counter_name' => 'Rage',
            'current_uses' => 2,
            'max_uses' => 3,
            'reset_timing' => 'L',
        ]);

        $counters = $this->service->getCountersForCharacter($character);
        $counter = $counters->first();

        $this->assertArrayHasKey('slug', $counter);
        $this->assertEquals('phb:barbarian:rage', $counter['slug']);
    }

    #[Test]
    public function it_generates_slug_with_kebab_case_counter_name(): void
    {
        $character = Character::factory()->create();

        CharacterCounter::factory()->create([
            'character_id' => $character->id,
            'source_type' => 'class',
            'source_slug' => 'phb:cleric',
            'counter_name' => 'Channel Divinity',
            'current_uses' => null,
            'max_uses' => 1,
            'reset_timing' => 'S',
        ]);

        $counters = $this->service->getCountersForCharacter($character);
        $counter = $counters->first();

        $this->assertEquals('phb:cleric:channel-divinity', $counter['slug']);
    }
}
