<?php

namespace Tests\Unit\Services\ChoiceHandlers;

use App\DTOs\AsiChoiceResult;
use App\DTOs\PendingChoice;
use App\Exceptions\ChoiceNotUndoableException;
use App\Exceptions\InvalidSelectionException;
use App\Models\AbilityScore;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use App\Models\Feat;
use App\Services\AsiChoiceService;
use App\Services\AvailableFeatsService;
use App\Services\ChoiceHandlers\AsiChoiceHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AsiChoiceHandlerTest extends TestCase
{
    use RefreshDatabase;

    private AsiChoiceService $asiService;

    private AvailableFeatsService $availableFeatsService;

    private AsiChoiceHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        // Create ability scores in database (use firstOrCreate to avoid duplicates)
        AbilityScore::firstOrCreate(['code' => 'STR'], ['name' => 'Strength']);
        AbilityScore::firstOrCreate(['code' => 'DEX'], ['name' => 'Dexterity']);
        AbilityScore::firstOrCreate(['code' => 'CON'], ['name' => 'Constitution']);
        AbilityScore::firstOrCreate(['code' => 'INT'], ['name' => 'Intelligence']);
        AbilityScore::firstOrCreate(['code' => 'WIS'], ['name' => 'Wisdom']);
        AbilityScore::firstOrCreate(['code' => 'CHA'], ['name' => 'Charisma']);

        // Create a few feats in database
        Feat::factory()->create(['name' => 'Alert', 'slug' => 'alert']);
        Feat::factory()->create(['name' => 'Lucky', 'slug' => 'lucky']);

        $this->asiService = Mockery::mock(AsiChoiceService::class);
        $this->availableFeatsService = Mockery::mock(AvailableFeatsService::class);
        // Default mock behavior: return empty Eloquent collection for any getAvailableFeats call
        $this->availableFeatsService->shouldReceive('getAvailableFeats')
            ->andReturn(new \Illuminate\Database\Eloquent\Collection);
        $this->handler = new AsiChoiceHandler($this->asiService, $this->availableFeatsService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_returns_asi_or_feat_as_type(): void
    {
        $this->assertEquals('asi_or_feat', $this->handler->getType());
    }

    #[Test]
    public function it_returns_empty_collection_when_no_asi_choices_remaining(): void
    {
        $character = Character::factory()->make([
            'id' => 1,
            'asi_choices_remaining' => 0,
        ]);

        $choices = $this->handler->getChoices($character);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $choices);
        $this->assertEmpty($choices);
    }

    #[Test]
    public function it_returns_empty_collection_when_no_primary_class(): void
    {
        $character = Character::factory()->make([
            'id' => 1,
            'asi_choices_remaining' => 1,
        ]);

        // Mock the characterClasses relationship to return empty collection
        $character->setRelation('characterClasses', collect());

        $choices = $this->handler->getChoices($character);

        $this->assertEmpty($choices);
    }

    #[Test]
    public function it_returns_one_choice_when_one_asi_remaining(): void
    {
        $class = CharacterClass::factory()->make([
            'id' => 1,
            'name' => 'Fighter',
        ]);

        $character = Character::factory()->make([
            'id' => 1,
            'asi_choices_remaining' => 1,
            'strength' => 16,
            'dexterity' => 14,
            'constitution' => 15,
            'intelligence' => 10,
            'wisdom' => 12,
            'charisma' => 8,
        ]);

        // Mock the primary class
        $pivot = CharacterClassPivot::factory()->make([
            'is_primary' => true,
            'level' => 4,
        ]);
        $pivot->setRelation('characterClass', $class);
        $character->setRelation('characterClasses', collect([$pivot]));

        $choices = $this->handler->getChoices($character);

        $this->assertCount(1, $choices);
        $this->assertInstanceOf(PendingChoice::class, $choices->first());
        $this->assertEquals('asi_or_feat', $choices->first()->type);
        $this->assertEquals('class', $choices->first()->source);
        $this->assertEquals('Fighter', $choices->first()->sourceName);
        $this->assertEquals(1, $choices->first()->quantity);
        $this->assertEquals(1, $choices->first()->remaining);
    }

    #[Test]
    public function it_returns_multiple_choices_when_multiple_asis_remaining(): void
    {
        $class = CharacterClass::factory()->make([
            'id' => 1,
            'name' => 'Fighter',
        ]);

        $character = Character::factory()->make([
            'id' => 1,
            'asi_choices_remaining' => 2,
            'strength' => 16,
            'dexterity' => 14,
            'constitution' => 15,
            'intelligence' => 10,
            'wisdom' => 12,
            'charisma' => 8,
        ]);

        // Mock the primary class
        $pivot = CharacterClassPivot::factory()->make([
            'is_primary' => true,
            'level' => 8,
        ]);
        $pivot->setRelation('characterClass', $class);
        $character->setRelation('characterClasses', collect([$pivot]));

        $choices = $this->handler->getChoices($character);

        $this->assertCount(2, $choices);
        $this->assertTrue($choices->every(fn ($choice) => $choice instanceof PendingChoice));
        $this->assertTrue($choices->every(fn ($choice) => $choice->type === 'asi_or_feat'));
    }

    #[Test]
    public function it_includes_ability_scores_in_metadata(): void
    {
        $class = CharacterClass::factory()->make([
            'id' => 1,
            'name' => 'Fighter',
        ]);

        $character = Character::factory()->make([
            'id' => 1,
            'asi_choices_remaining' => 1,
            'strength' => 16,
            'dexterity' => 14,
            'constitution' => 15,
            'intelligence' => 10,
            'wisdom' => 12,
            'charisma' => 8,
        ]);

        // Mock the primary class
        $pivot = CharacterClassPivot::factory()->make([
            'is_primary' => true,
            'level' => 4,
        ]);
        $pivot->setRelation('characterClass', $class);
        $character->setRelation('characterClasses', collect([$pivot]));

        $choices = $this->handler->getChoices($character);
        $choice = $choices->first();

        $this->assertArrayHasKey('ability_scores', $choice->metadata);
        $this->assertArrayHasKey('choice_options', $choice->metadata);
        $this->assertEquals(['asi', 'feat'], $choice->metadata['choice_options']);
        $this->assertEquals(2, $choice->metadata['asi_points']);
        $this->assertEquals(20, $choice->metadata['max_ability_score']);
    }

    #[Test]
    public function it_includes_options_endpoint_for_feats(): void
    {
        $class = CharacterClass::factory()->make([
            'id' => 1,
            'name' => 'Fighter',
        ]);

        $character = Character::factory()->make([
            'id' => 1,
            'public_id' => 'test-char-456',
            'asi_choices_remaining' => 1,
            'strength' => 16,
            'dexterity' => 14,
            'constitution' => 15,
            'intelligence' => 10,
            'wisdom' => 12,
            'charisma' => 8,
        ]);

        // Mock the primary class
        $pivot = CharacterClassPivot::factory()->make([
            'is_primary' => true,
            'level' => 4,
        ]);
        $pivot->setRelation('characterClass', $class);
        $character->setRelation('characterClasses', collect([$pivot]));

        $choices = $this->handler->getChoices($character);
        $choice = $choices->first();

        $this->assertEquals('/api/v1/characters/test-char-456/available-feats?source=asi', $choice->optionsEndpoint);
    }

    #[Test]
    public function it_calls_apply_feat_choice_when_type_is_feat(): void
    {
        $character = Character::factory()->create(['id' => 1]);
        $feat = Feat::factory()->create(['id' => 42]);

        $choice = new PendingChoice(
            id: 'asi_or_feat:class:1:4:asi_1',
            type: 'asi_or_feat',
            subtype: null,
            source: 'class',
            sourceName: 'Fighter',
            levelGranted: 4,
            required: false,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: null,
            optionsEndpoint: '/api/v1/feats',
            metadata: [],
        );

        $result = new AsiChoiceResult(
            choiceType: 'feat',
            asiChoicesRemaining: 0,
            abilityIncreases: [],
            newAbilityScores: [],
            feat: ['id' => 42, 'name' => $feat->name, 'slug' => $feat->slug],
            proficienciesGained: [],
            spellsGained: [],
        );

        $this->asiService->shouldReceive('applyFeatChoice')
            ->once()
            ->with($character, Mockery::on(fn ($arg) => $arg instanceof Feat && $arg->id === 42))
            ->andReturn($result);

        $this->handler->resolve($character, $choice, [
            'type' => 'feat',
            'feat_slug' => $feat->slug,
        ]);

        $this->assertTrue(true); // Assertion to avoid risky test warning
    }

    #[Test]
    public function it_calls_apply_ability_increase_when_type_is_asi(): void
    {
        $character = Character::factory()->create(['id' => 1]);

        $choice = new PendingChoice(
            id: 'asi_or_feat:class:1:4:asi_1',
            type: 'asi_or_feat',
            subtype: null,
            source: 'class',
            sourceName: 'Fighter',
            levelGranted: 4,
            required: false,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: null,
            optionsEndpoint: '/api/v1/feats',
            metadata: [],
        );

        $increases = ['STR' => 2];

        $result = new AsiChoiceResult(
            choiceType: 'ability_increase',
            asiChoicesRemaining: 0,
            abilityIncreases: $increases,
            newAbilityScores: [],
            feat: null,
            proficienciesGained: [],
            spellsGained: [],
        );

        $this->asiService->shouldReceive('applyAbilityIncrease')
            ->once()
            ->with($character, $increases)
            ->andReturn($result);

        $this->handler->resolve($character, $choice, [
            'type' => 'asi',
            'increases' => $increases,
        ]);

        $this->assertTrue(true); // Assertion to avoid risky test warning
    }

    #[Test]
    public function it_throws_invalid_selection_exception_when_type_is_missing(): void
    {
        $this->expectException(InvalidSelectionException::class);

        $character = Character::factory()->make(['id' => 1]);

        $choice = new PendingChoice(
            id: 'asi_or_feat:class:1:4:asi_1',
            type: 'asi_or_feat',
            subtype: null,
            source: 'class',
            sourceName: 'Fighter',
            levelGranted: 4,
            required: false,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: null,
            optionsEndpoint: '/api/v1/feats',
            metadata: [],
        );

        $this->handler->resolve($character, $choice, []);
    }

    #[Test]
    public function it_throws_invalid_selection_exception_when_type_is_invalid(): void
    {
        $this->expectException(InvalidSelectionException::class);

        $character = Character::factory()->make(['id' => 1]);

        $choice = new PendingChoice(
            id: 'asi_or_feat:class:1:4:asi_1',
            type: 'asi_or_feat',
            subtype: null,
            source: 'class',
            sourceName: 'Fighter',
            levelGranted: 4,
            required: false,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: null,
            optionsEndpoint: '/api/v1/feats',
            metadata: [],
        );

        $this->handler->resolve($character, $choice, ['type' => 'invalid']);
    }

    #[Test]
    public function it_throws_invalid_selection_exception_when_feat_slug_is_missing_for_feat_type(): void
    {
        $this->expectException(InvalidSelectionException::class);

        $character = Character::factory()->make(['id' => 1]);

        $choice = new PendingChoice(
            id: 'asi_or_feat:class:1:4:asi_1',
            type: 'asi_or_feat',
            subtype: null,
            source: 'class',
            sourceName: 'Fighter',
            levelGranted: 4,
            required: false,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: null,
            optionsEndpoint: '/api/v1/feats',
            metadata: [],
        );

        $this->handler->resolve($character, $choice, ['type' => 'feat']);
    }

    #[Test]
    public function it_throws_invalid_selection_exception_when_increases_is_missing_for_asi_type(): void
    {
        $this->expectException(InvalidSelectionException::class);

        $character = Character::factory()->make(['id' => 1]);

        $choice = new PendingChoice(
            id: 'asi_or_feat:class:1:4:asi_1',
            type: 'asi_or_feat',
            subtype: null,
            source: 'class',
            sourceName: 'Fighter',
            levelGranted: 4,
            required: false,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: null,
            optionsEndpoint: '/api/v1/feats',
            metadata: [],
        );

        $this->handler->resolve($character, $choice, ['type' => 'asi']);
    }

    #[Test]
    public function it_throws_invalid_selection_exception_when_increases_is_empty_for_asi_type(): void
    {
        $this->expectException(InvalidSelectionException::class);

        $character = Character::factory()->make(['id' => 1]);

        $choice = new PendingChoice(
            id: 'asi_or_feat:class:1:4:asi_1',
            type: 'asi_or_feat',
            subtype: null,
            source: 'class',
            sourceName: 'Fighter',
            levelGranted: 4,
            required: false,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: null,
            optionsEndpoint: '/api/v1/feats',
            metadata: [],
        );

        $this->handler->resolve($character, $choice, [
            'type' => 'asi',
            'increases' => [],
        ]);
    }

    #[Test]
    public function it_returns_false_for_can_undo(): void
    {
        $character = Character::factory()->make(['id' => 1]);

        $choice = new PendingChoice(
            id: 'asi_or_feat:class:1:4:asi_1',
            type: 'asi_or_feat',
            subtype: null,
            source: 'class',
            sourceName: 'Fighter',
            levelGranted: 4,
            required: false,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: null,
            optionsEndpoint: '/api/v1/feats',
            metadata: [],
        );

        $this->assertFalse($this->handler->canUndo($character, $choice));
    }

    #[Test]
    public function it_throws_choice_not_undoable_exception_for_undo(): void
    {
        $this->expectException(ChoiceNotUndoableException::class);

        $character = Character::factory()->make(['id' => 1]);

        $choice = new PendingChoice(
            id: 'asi_or_feat:class:1:4:asi_1',
            type: 'asi_or_feat',
            subtype: null,
            source: 'class',
            sourceName: 'Fighter',
            levelGranted: 4,
            required: false,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: null,
            optionsEndpoint: '/api/v1/feats',
            metadata: [],
        );

        $this->handler->undo($character, $choice);
    }
}
