<?php

namespace Tests\Unit\Services;

use App\Enums\ResetTiming;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterFeature;
use App\Models\ClassCounter;
use App\Models\ClassFeature;
use App\Services\FeatureUseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FeatureUseServiceTest extends TestCase
{
    use RefreshDatabase;

    private FeatureUseService $service;

    private CharacterClass $fighterClass;

    private Character $character;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FeatureUseService::class);
        $this->createFixtures();
    }

    private function createFixtures(): void
    {
        $this->fighterClass = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter',
            'slug' => 'fighter',
        ]);

        $this->character = Character::factory()
            ->withClass($this->fighterClass)
            ->create();
    }

    // =============================
    // getFeaturesWithUses()
    // =============================

    #[Test]
    public function it_gets_features_with_uses_for_character(): void
    {
        $actionSurge = ClassFeature::factory()
            ->forClass($this->fighterClass)
            ->create([
                'feature_name' => 'Action Surge',
                'level' => 2,
                'resets_on' => ResetTiming::SHORT_REST,
            ]);

        CharacterFeature::create([
            'character_id' => $this->character->id,
            'feature_type' => ClassFeature::class,
            'feature_id' => $actionSurge->id,
            'feature_slug' => 'action-surge',
            'source' => 'class',
            'level_acquired' => 2,
            'max_uses' => 1,
            'uses_remaining' => 1,
        ]);

        $result = $this->service->getFeaturesWithUses($this->character);

        expect($result)->toHaveCount(1);
        expect($result->first())->toMatchArray([
            'feature_name' => 'Action Surge',
            'uses_remaining' => 1,
            'max_uses' => 1,
            'resets_on' => 'short_rest',
            'source' => 'class',
        ]);
    }

    #[Test]
    public function it_excludes_features_without_limited_uses(): void
    {
        // Feature WITH limited uses
        $actionSurge = ClassFeature::factory()
            ->forClass($this->fighterClass)
            ->create([
                'feature_name' => 'Action Surge',
                'resets_on' => ResetTiming::SHORT_REST,
            ]);

        CharacterFeature::create([
            'character_id' => $this->character->id,
            'feature_type' => ClassFeature::class,
            'feature_id' => $actionSurge->id,
            'feature_slug' => 'action-surge',
            'source' => 'class',
            'level_acquired' => 2,
            'max_uses' => 1,
            'uses_remaining' => 1,
        ]);

        // Feature WITHOUT limited uses
        $fightingStyle = ClassFeature::factory()
            ->forClass($this->fighterClass)
            ->create([
                'feature_name' => 'Fighting Style',
                'resets_on' => null,
            ]);

        CharacterFeature::create([
            'character_id' => $this->character->id,
            'feature_type' => ClassFeature::class,
            'feature_id' => $fightingStyle->id,
            'feature_slug' => 'fighting-style',
            'source' => 'class',
            'level_acquired' => 1,
            'max_uses' => null,
            'uses_remaining' => null,
        ]);

        $result = $this->service->getFeaturesWithUses($this->character);

        expect($result)->toHaveCount(1);
        expect($result->first()['feature_name'])->toBe('Action Surge');
    }

    #[Test]
    public function it_returns_empty_collection_for_character_with_no_limited_features(): void
    {
        $result = $this->service->getFeaturesWithUses($this->character);

        expect($result)->toBeEmpty();
    }

    // =============================
    // useFeature()
    // =============================

    #[Test]
    public function it_uses_feature_decrements_remaining(): void
    {
        $actionSurge = ClassFeature::factory()
            ->forClass($this->fighterClass)
            ->create(['feature_name' => 'Action Surge']);

        $characterFeature = CharacterFeature::create([
            'character_id' => $this->character->id,
            'feature_type' => ClassFeature::class,
            'feature_id' => $actionSurge->id,
            'feature_slug' => 'action-surge',
            'source' => 'class',
            'level_acquired' => 2,
            'max_uses' => 1,
            'uses_remaining' => 1,
        ]);

        $result = $this->service->useFeature($this->character, $characterFeature->id);

        expect($result)->toBeTrue();
        expect($characterFeature->fresh()->uses_remaining)->toBe(0);
    }

    #[Test]
    public function it_returns_false_when_no_uses_remaining(): void
    {
        $actionSurge = ClassFeature::factory()
            ->forClass($this->fighterClass)
            ->create(['feature_name' => 'Action Surge']);

        $characterFeature = CharacterFeature::create([
            'character_id' => $this->character->id,
            'feature_type' => ClassFeature::class,
            'feature_id' => $actionSurge->id,
            'feature_slug' => 'action-surge',
            'source' => 'class',
            'level_acquired' => 2,
            'max_uses' => 1,
            'uses_remaining' => 0,
        ]);

        $result = $this->service->useFeature($this->character, $characterFeature->id);

        expect($result)->toBeFalse();
        expect($characterFeature->fresh()->uses_remaining)->toBe(0);
    }

    #[Test]
    public function it_returns_false_for_nonexistent_feature(): void
    {
        $result = $this->service->useFeature($this->character, 99999);

        expect($result)->toBeFalse();
    }

    #[Test]
    public function it_returns_false_for_feature_belonging_to_different_character(): void
    {
        $otherCharacter = Character::factory()->create();

        $actionSurge = ClassFeature::factory()
            ->forClass($this->fighterClass)
            ->create(['feature_name' => 'Action Surge']);

        $characterFeature = CharacterFeature::create([
            'character_id' => $otherCharacter->id,
            'feature_type' => ClassFeature::class,
            'feature_id' => $actionSurge->id,
            'feature_slug' => 'action-surge',
            'source' => 'class',
            'level_acquired' => 2,
            'max_uses' => 1,
            'uses_remaining' => 1,
        ]);

        $result = $this->service->useFeature($this->character, $characterFeature->id);

        expect($result)->toBeFalse();
    }

    // =============================
    // resetFeature()
    // =============================

    #[Test]
    public function it_resets_feature_to_max_uses(): void
    {
        $actionSurge = ClassFeature::factory()
            ->forClass($this->fighterClass)
            ->create(['feature_name' => 'Action Surge']);

        $characterFeature = CharacterFeature::create([
            'character_id' => $this->character->id,
            'feature_type' => ClassFeature::class,
            'feature_id' => $actionSurge->id,
            'feature_slug' => 'action-surge',
            'source' => 'class',
            'level_acquired' => 2,
            'max_uses' => 1,
            'uses_remaining' => 0,
        ]);

        $this->service->resetFeature($this->character, $characterFeature->id);

        expect($characterFeature->fresh()->uses_remaining)->toBe(1);
    }

    // =============================
    // resetByRechargeType()
    // =============================

    #[Test]
    public function it_resets_by_short_rest_timing(): void
    {
        // Short rest feature
        $actionSurge = ClassFeature::factory()
            ->forClass($this->fighterClass)
            ->create([
                'feature_name' => 'Action Surge',
                'resets_on' => ResetTiming::SHORT_REST,
            ]);

        $shortRestFeature = CharacterFeature::create([
            'character_id' => $this->character->id,
            'feature_type' => ClassFeature::class,
            'feature_id' => $actionSurge->id,
            'feature_slug' => 'action-surge',
            'source' => 'class',
            'level_acquired' => 2,
            'max_uses' => 1,
            'uses_remaining' => 0,
        ]);

        // Long rest feature (should NOT be reset)
        $rage = ClassFeature::factory()
            ->forClass($this->fighterClass)
            ->create([
                'feature_name' => 'Rage',
                'resets_on' => ResetTiming::LONG_REST,
            ]);

        $longRestFeature = CharacterFeature::create([
            'character_id' => $this->character->id,
            'feature_type' => ClassFeature::class,
            'feature_id' => $rage->id,
            'feature_slug' => 'rage',
            'source' => 'class',
            'level_acquired' => 1,
            'max_uses' => 2,
            'uses_remaining' => 0,
        ]);

        $resetCount = $this->service->resetByRechargeType($this->character, ResetTiming::SHORT_REST);

        expect($resetCount)->toBe(1);
        expect($shortRestFeature->fresh()->uses_remaining)->toBe(1);
        expect($longRestFeature->fresh()->uses_remaining)->toBe(0); // Unchanged
    }

    #[Test]
    public function it_resets_by_long_rest_timing_includes_all_types(): void
    {
        // Short rest feature
        $actionSurge = ClassFeature::factory()
            ->forClass($this->fighterClass)
            ->create([
                'feature_name' => 'Action Surge',
                'resets_on' => ResetTiming::SHORT_REST,
            ]);

        $shortRestFeature = CharacterFeature::create([
            'character_id' => $this->character->id,
            'feature_type' => ClassFeature::class,
            'feature_id' => $actionSurge->id,
            'feature_slug' => 'action-surge',
            'source' => 'class',
            'level_acquired' => 2,
            'max_uses' => 1,
            'uses_remaining' => 0,
        ]);

        // Long rest feature
        $rage = ClassFeature::factory()
            ->forClass($this->fighterClass)
            ->create([
                'feature_name' => 'Rage',
                'resets_on' => ResetTiming::LONG_REST,
            ]);

        $longRestFeature = CharacterFeature::create([
            'character_id' => $this->character->id,
            'feature_type' => ClassFeature::class,
            'feature_id' => $rage->id,
            'feature_slug' => 'rage',
            'source' => 'class',
            'level_acquired' => 1,
            'max_uses' => 2,
            'uses_remaining' => 0,
        ]);

        // Reset all for long rest
        $resetCount = $this->service->resetByRechargeType(
            $this->character,
            ResetTiming::SHORT_REST,
            ResetTiming::LONG_REST,
            ResetTiming::DAWN
        );

        expect($resetCount)->toBe(2);
        expect($shortRestFeature->fresh()->uses_remaining)->toBe(1);
        expect($longRestFeature->fresh()->uses_remaining)->toBe(2);
    }

    // =============================
    // initializeUsesForFeature()
    // =============================

    #[Test]
    public function it_initializes_uses_from_counter(): void
    {
        // Create a counter for this class at level 1
        ClassCounter::factory()
            ->forClass($this->fighterClass)
            ->atLevel(1)
            ->create([
                'counter_name' => 'Action Surge',
                'counter_value' => 1,
                'reset_timing' => 'S',
            ]);

        $actionSurge = ClassFeature::factory()
            ->forClass($this->fighterClass)
            ->create([
                'feature_name' => 'Action Surge',
                'level' => 2,
            ]);

        $characterFeature = CharacterFeature::create([
            'character_id' => $this->character->id,
            'feature_type' => ClassFeature::class,
            'feature_id' => $actionSurge->id,
            'feature_slug' => 'action-surge',
            'source' => 'class',
            'level_acquired' => 2,
            'max_uses' => null,
            'uses_remaining' => null,
        ]);

        $this->service->initializeUsesForFeature($characterFeature, classLevel: 2);

        $characterFeature->refresh();
        expect($characterFeature->max_uses)->toBe(1);
        expect($characterFeature->uses_remaining)->toBe(1);
    }

    #[Test]
    public function it_uses_counter_value_for_characters_level(): void
    {
        // Level 1 counter
        ClassCounter::factory()
            ->forClass($this->fighterClass)
            ->atLevel(1)
            ->create([
                'counter_name' => 'Rage',
                'counter_value' => 2,
            ]);

        // Level 3 counter (higher value)
        ClassCounter::factory()
            ->forClass($this->fighterClass)
            ->atLevel(3)
            ->create([
                'counter_name' => 'Rage',
                'counter_value' => 3,
            ]);

        $rage = ClassFeature::factory()
            ->forClass($this->fighterClass)
            ->create([
                'feature_name' => 'Rage',
                'level' => 1,
            ]);

        $characterFeature = CharacterFeature::create([
            'character_id' => $this->character->id,
            'feature_type' => ClassFeature::class,
            'feature_id' => $rage->id,
            'feature_slug' => 'rage',
            'source' => 'class',
            'level_acquired' => 1,
        ]);

        // Initialize at level 5 - should get counter value for level 3 (highest <= 5)
        $this->service->initializeUsesForFeature($characterFeature, classLevel: 5);

        expect($characterFeature->fresh()->max_uses)->toBe(3);
    }

    #[Test]
    public function it_handles_features_without_counters(): void
    {
        $fightingStyle = ClassFeature::factory()
            ->forClass($this->fighterClass)
            ->create(['feature_name' => 'Fighting Style']);

        $characterFeature = CharacterFeature::create([
            'character_id' => $this->character->id,
            'feature_type' => ClassFeature::class,
            'feature_id' => $fightingStyle->id,
            'feature_slug' => 'fighting-style',
            'source' => 'class',
            'level_acquired' => 1,
        ]);

        // Should not throw - just leave uses as null
        $this->service->initializeUsesForFeature($characterFeature, classLevel: 1);

        expect($characterFeature->fresh()->max_uses)->toBeNull();
    }

    // =============================
    // recalculateMaxUses()
    // =============================

    #[Test]
    public function it_recalculates_on_level_up(): void
    {
        // Create counters at different levels
        ClassCounter::factory()
            ->forClass($this->fighterClass)
            ->atLevel(1)
            ->create([
                'counter_name' => 'Rage',
                'counter_value' => 2,
            ]);

        ClassCounter::factory()
            ->forClass($this->fighterClass)
            ->atLevel(6)
            ->create([
                'counter_name' => 'Rage',
                'counter_value' => 4,
            ]);

        $rage = ClassFeature::factory()
            ->forClass($this->fighterClass)
            ->create([
                'feature_name' => 'Rage',
                'level' => 1,
            ]);

        $characterFeature = CharacterFeature::create([
            'character_id' => $this->character->id,
            'feature_type' => ClassFeature::class,
            'feature_id' => $rage->id,
            'feature_slug' => 'rage',
            'source' => 'class',
            'level_acquired' => 1,
            'max_uses' => 2,
            'uses_remaining' => 2,
        ]);

        // Level up the character to 6
        $this->character->characterClasses()->update(['level' => 6]);

        $this->service->recalculateMaxUses($this->character);

        $characterFeature->refresh();
        expect($characterFeature->max_uses)->toBe(4);
        // uses_remaining should also be increased if it was at max
        expect($characterFeature->uses_remaining)->toBe(4);
    }

    #[Test]
    public function it_does_not_decrease_current_uses_on_recalculate(): void
    {
        // Edge case: if max increases but user had already used some
        ClassCounter::factory()
            ->forClass($this->fighterClass)
            ->atLevel(1)
            ->create([
                'counter_name' => 'Rage',
                'counter_value' => 2,
            ]);

        ClassCounter::factory()
            ->forClass($this->fighterClass)
            ->atLevel(6)
            ->create([
                'counter_name' => 'Rage',
                'counter_value' => 4,
            ]);

        $rage = ClassFeature::factory()
            ->forClass($this->fighterClass)
            ->create([
                'feature_name' => 'Rage',
                'level' => 1,
            ]);

        $characterFeature = CharacterFeature::create([
            'character_id' => $this->character->id,
            'feature_type' => ClassFeature::class,
            'feature_id' => $rage->id,
            'feature_slug' => 'rage',
            'source' => 'class',
            'level_acquired' => 1,
            'max_uses' => 2,
            'uses_remaining' => 1, // Used one rage
        ]);

        $this->character->characterClasses()->update(['level' => 6]);

        $this->service->recalculateMaxUses($this->character);

        $characterFeature->refresh();
        expect($characterFeature->max_uses)->toBe(4);
        // uses_remaining should increase by the delta (4-2=2 extra uses)
        expect($characterFeature->uses_remaining)->toBe(3);
    }
}
