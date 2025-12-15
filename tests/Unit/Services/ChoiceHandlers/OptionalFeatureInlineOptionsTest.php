<?php

/**
 * Integration tests for OptionalFeatureChoiceHandler inline options.
 *
 * Tests the fix for issue #622: Options should be returned inline
 * with already-selected features excluded.
 */

use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use App\Models\ClassCounter;
use App\Models\FeatureSelection;
use App\Models\OptionalFeature;
use App\Services\ChoiceHandlers\OptionalFeatureChoiceHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->handler = new OptionalFeatureChoiceHandler;
});

describe('inline options filtering (#622)', function () {
    it('returns options inline instead of optionsEndpoint', function () {
        // Create warlock class with invocations counter
        $warlock = CharacterClass::factory()->create([
            'slug' => 'test:warlock',
            'name' => 'Warlock',
        ]);

        ClassCounter::factory()->create([
            'class_id' => $warlock->id,
            'level' => 2,
            'counter_name' => 'Eldritch Invocations Known',
            'counter_value' => 2,
        ]);

        // Create optional features for warlock (no level requirement)
        $invocation1 = OptionalFeature::factory()
            ->invocation()
            ->forClass($warlock)
            ->create(['name' => 'Agonizing Blast', 'level_requirement' => null]);

        $invocation2 = OptionalFeature::factory()
            ->invocation()
            ->forClass($warlock)
            ->create(['name' => 'Eldritch Sight', 'level_requirement' => null]);

        // Create character at level 2
        $character = Character::factory()->create();
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $warlock->slug,
            'level' => 2,
            'is_primary' => true,
        ]);

        $character->load(['characterClasses.characterClass.counters']);

        $choices = $this->handler->getChoices($character);

        expect($choices)->toHaveCount(1);

        $choice = $choices->first();
        expect($choice->optionsEndpoint)->toBeNull()
            ->and($choice->options)->toBeArray()
            ->and($choice->options)->toHaveCount(2)
            ->and(collect($choice->options)->pluck('slug')->toArray())
            ->toContain($invocation1->slug)
            ->toContain($invocation2->slug);
    });

    it('excludes already-selected features from options', function () {
        // Create warlock class with invocations counter
        $warlock = CharacterClass::factory()->create([
            'slug' => 'test:warlock',
            'name' => 'Warlock',
        ]);

        ClassCounter::factory()->create([
            'class_id' => $warlock->id,
            'level' => 2,
            'counter_name' => 'Eldritch Invocations Known',
            'counter_value' => 3,
        ]);

        // Create 3 invocations (no level requirement)
        $invocation1 = OptionalFeature::factory()
            ->invocation()
            ->forClass($warlock)
            ->create(['name' => 'Agonizing Blast', 'level_requirement' => null]);

        $invocation2 = OptionalFeature::factory()
            ->invocation()
            ->forClass($warlock)
            ->create(['name' => 'Eldritch Sight', 'level_requirement' => null]);

        $invocation3 = OptionalFeature::factory()
            ->invocation()
            ->forClass($warlock)
            ->create(['name' => 'Devils Sight', 'level_requirement' => null]);

        // Create character at level 5
        $character = Character::factory()->create();
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $warlock->slug,
            'level' => 5,
            'is_primary' => true,
        ]);

        // Character already selected invocation1
        FeatureSelection::factory()->create([
            'character_id' => $character->id,
            'optional_feature_slug' => $invocation1->slug,
            'class_slug' => $warlock->slug,
            'level_acquired' => 2,
        ]);

        $character->load(['characterClasses.characterClass.counters', 'featureSelections.optionalFeature']);

        $choices = $this->handler->getChoices($character);

        expect($choices)->toHaveCount(1);

        $choice = $choices->first();
        $optionSlugs = collect($choice->options)->pluck('slug')->toArray();

        // Should have 2 options (invocation1 excluded)
        expect($optionSlugs)->toHaveCount(2)
            ->not->toContain($invocation1->slug)
            ->toContain($invocation2->slug)
            ->toContain($invocation3->slug);

        // Remaining should reflect already-selected
        expect($choice->remaining)->toBe(2); // 3 allowed - 1 selected = 2 remaining
    });

    it('returns empty options when all features are selected', function () {
        // Create sorcerer class with metamagic counter
        $sorcerer = CharacterClass::factory()->create([
            'slug' => 'test:sorcerer',
            'name' => 'Sorcerer',
        ]);

        ClassCounter::factory()->create([
            'class_id' => $sorcerer->id,
            'level' => 3,
            'counter_name' => 'Metamagic Known',
            'counter_value' => 2,
        ]);

        // Create 2 metamagic options
        $meta1 = OptionalFeature::factory()
            ->metamagic()
            ->forClass($sorcerer)
            ->create(['name' => 'Quickened Spell']);

        $meta2 = OptionalFeature::factory()
            ->metamagic()
            ->forClass($sorcerer)
            ->create(['name' => 'Twinned Spell']);

        // Create character who selected both
        $character = Character::factory()->create();
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $sorcerer->slug,
            'level' => 3,
            'is_primary' => true,
        ]);

        FeatureSelection::factory()->create([
            'character_id' => $character->id,
            'optional_feature_slug' => $meta1->slug,
            'class_slug' => $sorcerer->slug,
        ]);

        FeatureSelection::factory()->create([
            'character_id' => $character->id,
            'optional_feature_slug' => $meta2->slug,
            'class_slug' => $sorcerer->slug,
        ]);

        $character->load(['characterClasses.characterClass.counters', 'featureSelections.optionalFeature']);

        $choices = $this->handler->getChoices($character);

        expect($choices)->toHaveCount(1);

        $choice = $choices->first();
        expect($choice->options)->toBeArray()
            ->and($choice->options)->toBeEmpty()
            ->and($choice->remaining)->toBe(0);
    });

    it('filters options by level requirement', function () {
        $warlock = CharacterClass::factory()->create([
            'slug' => 'test:warlock',
            'name' => 'Warlock',
        ]);

        ClassCounter::factory()->create([
            'class_id' => $warlock->id,
            'level' => 2,
            'counter_name' => 'Eldritch Invocations Known',
            'counter_value' => 2,
        ]);

        // Low level invocation (no requirement)
        $lowLevel = OptionalFeature::factory()
            ->invocation()
            ->forClass($warlock)
            ->create([
                'name' => 'Basic Invocation',
                'level_requirement' => null,
            ]);

        // High level invocation (requires level 15)
        $highLevel = OptionalFeature::factory()
            ->invocation()
            ->forClass($warlock)
            ->create([
                'name' => 'Advanced Invocation',
                'level_requirement' => 15,
            ]);

        // Character at level 5
        $character = Character::factory()->create();
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $warlock->slug,
            'level' => 5,
            'is_primary' => true,
        ]);

        $character->load(['characterClasses.characterClass.counters']);

        $choices = $this->handler->getChoices($character);
        $choice = $choices->first();

        $optionSlugs = collect($choice->options)->pluck('slug')->toArray();

        // Only low level should be available
        expect($optionSlugs)->toHaveCount(1)
            ->toContain($lowLevel->slug)
            ->not->toContain($highLevel->slug);
    });

    it('filters options by class association', function () {
        $warlock = CharacterClass::factory()->create([
            'slug' => 'test:warlock',
            'name' => 'Warlock',
        ]);

        $fighter = CharacterClass::factory()->create([
            'slug' => 'test:fighter',
            'name' => 'Fighter',
        ]);

        ClassCounter::factory()->create([
            'class_id' => $warlock->id,
            'level' => 2,
            'counter_name' => 'Eldritch Invocations Known',
            'counter_value' => 2,
        ]);

        // Warlock invocation (no level requirement)
        $warlockFeature = OptionalFeature::factory()
            ->invocation()
            ->forClass($warlock)
            ->create(['name' => 'Warlock Only', 'level_requirement' => null]);

        // Fighter maneuver (should not appear for warlock)
        $fighterFeature = OptionalFeature::factory()
            ->maneuver()
            ->forClass($fighter)
            ->create(['name' => 'Fighter Only', 'level_requirement' => null]);

        $character = Character::factory()->create();
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $warlock->slug,
            'level' => 2,
            'is_primary' => true,
        ]);

        $character->load(['characterClasses.characterClass.counters']);

        $choices = $this->handler->getChoices($character);
        $choice = $choices->first();

        $optionSlugs = collect($choice->options)->pluck('slug')->toArray();

        expect($optionSlugs)->toHaveCount(1)
            ->toContain($warlockFeature->slug)
            ->not->toContain($fighterFeature->slug);
    });

    it('includes expected fields in options array', function () {
        $warlock = CharacterClass::factory()->create([
            'slug' => 'test:warlock',
            'name' => 'Warlock',
        ]);

        ClassCounter::factory()->create([
            'class_id' => $warlock->id,
            'level' => 2,
            'counter_name' => 'Eldritch Invocations Known',
            'counter_value' => 2,
        ]);

        OptionalFeature::factory()
            ->invocation()
            ->forClass($warlock)
            ->create([
                'name' => 'Test Invocation',
                'description' => 'A test description',
                'level_requirement' => 5,
                'prerequisite_text' => 'Eldritch Blast cantrip',
            ]);

        $character = Character::factory()->create();
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $warlock->slug,
            'level' => 5,
            'is_primary' => true,
        ]);

        $character->load(['characterClasses.characterClass.counters']);

        $choices = $this->handler->getChoices($character);
        $option = $choices->first()->options[0];

        expect($option)->toHaveKeys(['slug', 'name', 'description', 'level_requirement', 'prerequisite_text'])
            ->and($option['name'])->toBe('Test Invocation')
            ->and($option['description'])->toBe('A test description')
            ->and($option['level_requirement'])->toBe(5)
            ->and($option['prerequisite_text'])->toBe('Eldritch Blast cantrip');
    });
});
