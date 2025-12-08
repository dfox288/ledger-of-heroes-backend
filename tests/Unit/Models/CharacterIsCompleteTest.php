<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\Race;
use App\Services\CharacterChoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterIsCompleteTest extends TestCase
{
    use RefreshDatabase;

    private Race $race;

    private CharacterClass $class;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test race and class without choices for simple base cases
        $this->race = Race::factory()->create(['full_slug' => 'test:human-complete']);
        $this->class = CharacterClass::factory()->create(['full_slug' => 'test:fighter-complete']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // =====================
    // Basic Requirements Tests (existing behavior)
    // =====================

    #[Test]
    public function it_returns_false_when_missing_race(): void
    {
        $character = Character::factory()
            ->withClass($this->class)
            ->withAbilityScores([
                'strength' => 15,
                'dexterity' => 14,
                'constitution' => 13,
                'intelligence' => 12,
                'wisdom' => 10,
                'charisma' => 8,
            ])
            ->create();

        $this->assertFalse($character->is_complete);
    }

    #[Test]
    public function it_returns_false_when_missing_class(): void
    {
        $character = Character::factory()
            ->withRace($this->race)
            ->withAbilityScores([
                'strength' => 15,
                'dexterity' => 14,
                'constitution' => 13,
                'intelligence' => 12,
                'wisdom' => 10,
                'charisma' => 8,
            ])
            ->create();

        $this->assertFalse($character->is_complete);
    }

    #[Test]
    public function it_returns_false_when_missing_ability_scores(): void
    {
        $character = Character::factory()
            ->withRace($this->race)
            ->withClass($this->class)
            ->create();

        $this->assertFalse($character->is_complete);
    }

    // =====================
    // Choice Verification Tests (new behavior)
    // =====================

    #[Test]
    public function it_returns_false_when_required_choices_are_pending(): void
    {
        $character = Character::factory()
            ->withRace($this->race)
            ->withClass($this->class)
            ->withAbilityScores([
                'strength' => 15,
                'dexterity' => 14,
                'constitution' => 13,
                'intelligence' => 12,
                'wisdom' => 10,
                'charisma' => 8,
            ])
            ->create();

        // Mock the CharacterChoiceService to return pending required choices
        $mockService = Mockery::mock(CharacterChoiceService::class);
        $mockService->shouldReceive('getSummary')
            ->andReturn([
                'total_pending' => 2,
                'required_pending' => 1,
                'optional_pending' => 1,
                'by_type' => ['proficiency' => 1, 'language' => 1],
                'by_source' => ['class' => 1, 'race' => 1],
            ]);

        $this->app->instance(CharacterChoiceService::class, $mockService);

        // Refresh the character to pick up the mock
        $character->refresh();

        $this->assertFalse($character->is_complete);
    }

    #[Test]
    public function it_returns_true_when_all_required_choices_are_resolved(): void
    {
        $character = Character::factory()
            ->withRace($this->race)
            ->withClass($this->class)
            ->withAbilityScores([
                'strength' => 15,
                'dexterity' => 14,
                'constitution' => 13,
                'intelligence' => 12,
                'wisdom' => 10,
                'charisma' => 8,
            ])
            ->create();

        // Mock the CharacterChoiceService to return no pending required choices
        $mockService = Mockery::mock(CharacterChoiceService::class);
        $mockService->shouldReceive('getSummary')
            ->andReturn([
                'total_pending' => 1,
                'required_pending' => 0, // No required pending
                'optional_pending' => 1,
                'by_type' => ['language' => 1],
                'by_source' => ['race' => 1],
            ]);

        $this->app->instance(CharacterChoiceService::class, $mockService);

        $character->refresh();

        $this->assertTrue($character->is_complete);
    }

    #[Test]
    public function it_returns_true_when_no_choices_exist(): void
    {
        $character = Character::factory()
            ->withRace($this->race)
            ->withClass($this->class)
            ->withAbilityScores([
                'strength' => 15,
                'dexterity' => 14,
                'constitution' => 13,
                'intelligence' => 12,
                'wisdom' => 10,
                'charisma' => 8,
            ])
            ->create();

        // Mock the CharacterChoiceService to return no pending choices at all
        $mockService = Mockery::mock(CharacterChoiceService::class);
        $mockService->shouldReceive('getSummary')
            ->andReturn([
                'total_pending' => 0,
                'required_pending' => 0,
                'optional_pending' => 0,
                'by_type' => [],
                'by_source' => [],
            ]);

        $this->app->instance(CharacterChoiceService::class, $mockService);

        $character->refresh();

        $this->assertTrue($character->is_complete);
    }

    // =====================
    // Validation Status Tests
    // =====================

    #[Test]
    public function it_includes_pending_choices_in_missing_array(): void
    {
        $character = Character::factory()
            ->withRace($this->race)
            ->withClass($this->class)
            ->withAbilityScores([
                'strength' => 15,
                'dexterity' => 14,
                'constitution' => 13,
                'intelligence' => 12,
                'wisdom' => 10,
                'charisma' => 8,
            ])
            ->create();

        // Mock the CharacterChoiceService to return pending required choices
        $mockService = Mockery::mock(CharacterChoiceService::class);
        $mockService->shouldReceive('getSummary')
            ->andReturn([
                'total_pending' => 2,
                'required_pending' => 2,
                'optional_pending' => 0,
                'by_type' => ['proficiency' => 1, 'spell' => 1],
                'by_source' => ['class' => 2],
            ]);

        $this->app->instance(CharacterChoiceService::class, $mockService);

        $character->refresh();

        $status = $character->validation_status;

        $this->assertFalse($status['is_complete']);
        $this->assertContains('pending_choices', $status['missing']);
    }

    #[Test]
    public function it_does_not_include_pending_choices_when_all_resolved(): void
    {
        $character = Character::factory()
            ->withRace($this->race)
            ->withClass($this->class)
            ->withAbilityScores([
                'strength' => 15,
                'dexterity' => 14,
                'constitution' => 13,
                'intelligence' => 12,
                'wisdom' => 10,
                'charisma' => 8,
            ])
            ->create();

        // Mock the CharacterChoiceService to return no pending required choices
        $mockService = Mockery::mock(CharacterChoiceService::class);
        $mockService->shouldReceive('getSummary')
            ->andReturn([
                'total_pending' => 0,
                'required_pending' => 0,
                'optional_pending' => 0,
                'by_type' => [],
                'by_source' => [],
            ]);

        $this->app->instance(CharacterChoiceService::class, $mockService);

        $character->refresh();

        $status = $character->validation_status;

        $this->assertTrue($status['is_complete']);
        $this->assertNotContains('pending_choices', $status['missing']);
        $this->assertEmpty($status['missing']);
    }

    // =====================
    // hasAllRequiredChoicesResolved Method Tests
    // =====================

    #[Test]
    public function it_has_all_required_choices_resolved_returns_true_when_required_pending_is_zero(): void
    {
        $character = Character::factory()
            ->withRace($this->race)
            ->withClass($this->class)
            ->create();

        $mockService = Mockery::mock(CharacterChoiceService::class);
        $mockService->shouldReceive('getSummary')
            ->andReturn(['required_pending' => 0]);

        $this->app->instance(CharacterChoiceService::class, $mockService);

        $this->assertTrue($character->hasAllRequiredChoicesResolved());
    }

    #[Test]
    public function it_has_all_required_choices_resolved_returns_false_when_required_pending_is_greater_than_zero(): void
    {
        $character = Character::factory()
            ->withRace($this->race)
            ->withClass($this->class)
            ->create();

        $mockService = Mockery::mock(CharacterChoiceService::class);
        $mockService->shouldReceive('getSummary')
            ->andReturn(['required_pending' => 3]);

        $this->app->instance(CharacterChoiceService::class, $mockService);

        $this->assertFalse($character->hasAllRequiredChoicesResolved());
    }

    // =====================
    // Dangling Reference Tests
    // =====================

    #[Test]
    public function it_returns_true_for_choices_when_race_is_dangling_reference(): void
    {
        // Create character with dangling race_slug (points to non-existent race)
        $character = Character::factory()
            ->withClass($this->class)
            ->create(['race_slug' => 'nonexistent:race']);

        // Service should NOT be called since we have a dangling reference
        $mockService = Mockery::mock(CharacterChoiceService::class);
        $mockService->shouldNotReceive('getSummary');

        $this->app->instance(CharacterChoiceService::class, $mockService);

        // Should return true (skip validation) when race is dangling
        $this->assertTrue($character->hasAllRequiredChoicesResolved());
    }

    #[Test]
    public function it_returns_true_for_choices_when_class_is_dangling_reference(): void
    {
        // Create character with dangling class_slug (points to non-existent class)
        $character = Character::factory()
            ->withRace($this->race)
            ->create();

        // Add a dangling class reference via the pivot
        \App\Models\CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => 'nonexistent:class',
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
        ]);

        $character->refresh();

        // Service should NOT be called since we have a dangling reference
        $mockService = Mockery::mock(CharacterChoiceService::class);
        $mockService->shouldNotReceive('getSummary');

        $this->app->instance(CharacterChoiceService::class, $mockService);

        // Should return true (skip validation) when class is dangling
        $this->assertTrue($character->hasAllRequiredChoicesResolved());
    }
}
