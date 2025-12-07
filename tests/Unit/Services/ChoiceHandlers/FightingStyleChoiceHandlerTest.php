<?php

namespace Tests\Unit\Services\ChoiceHandlers;

use App\DTOs\PendingChoice;
use App\Enums\OptionalFeatureType;
use App\Exceptions\ChoiceNotUndoableException;
use App\Exceptions\InvalidSelectionException;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use App\Models\FeatureSelection;
use App\Models\OptionalFeature;
use App\Services\ChoiceHandlers\FightingStyleChoiceHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FightingStyleChoiceHandlerTest extends TestCase
{
    use RefreshDatabase;

    private FightingStyleChoiceHandler $handler;

    private OptionalFeature $archery;

    private OptionalFeature $defense;

    private OptionalFeature $dueling;

    private CharacterClass $fighter;

    private CharacterClass $paladin;

    private CharacterClass $ranger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = new FightingStyleChoiceHandler;

        // Create fighting style optional features
        $this->archery = OptionalFeature::factory()->create([
            'name' => 'Archery',
            'slug' => 'archery',
            'feature_type' => OptionalFeatureType::FIGHTING_STYLE,
        ]);

        $this->defense = OptionalFeature::factory()->create([
            'name' => 'Defense',
            'slug' => 'defense',
            'feature_type' => OptionalFeatureType::FIGHTING_STYLE,
        ]);

        $this->dueling = OptionalFeature::factory()->create([
            'name' => 'Dueling',
            'slug' => 'dueling',
            'feature_type' => OptionalFeatureType::FIGHTING_STYLE,
        ]);

        // Create classes
        $this->fighter = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter',
        ]);

        $this->paladin = CharacterClass::factory()->create([
            'name' => 'Paladin',
            'slug' => 'paladin',
        ]);

        $this->ranger = CharacterClass::factory()->create([
            'name' => 'Ranger',
            'slug' => 'ranger',
        ]);
    }

    /** @test */
    public function it_returns_correct_type(): void
    {
        $this->assertEquals('fighting_style', $this->handler->getType());
    }

    /** @test */
    public function it_generates_pending_choice_for_fighter_level_1(): void
    {
        $character = Character::factory()->create();
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $this->fighter->full_slug,
            'level' => 1,
            'is_primary' => true,
        ]);

        $character->load('characterClasses.characterClass', 'featureSelections');

        $choices = $this->handler->getChoices($character);

        $this->assertCount(1, $choices);
        $choice = $choices->first();
        $this->assertInstanceOf(PendingChoice::class, $choice);
        $this->assertEquals('fighting_style', $choice->type);
        $this->assertNull($choice->subtype);
        $this->assertEquals('class', $choice->source);
        $this->assertEquals('Fighter', $choice->sourceName);
        $this->assertEquals(1, $choice->levelGranted);
        $this->assertTrue($choice->required);
        $this->assertEquals(1, $choice->quantity);
        $this->assertEquals(1, $choice->remaining);
        $this->assertEmpty($choice->selected);
        $this->assertCount(3, $choice->options);
        $this->assertEquals('/api/v1/lookups/optional-features?feature_type=fighting_style', $choice->optionsEndpoint);
    }

    /** @test */
    public function it_generates_pending_choice_for_paladin_level_2(): void
    {
        $character = Character::factory()->create();
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $this->paladin->full_slug,
            'level' => 2,
            'is_primary' => true,
        ]);

        $character->load('characterClasses.characterClass', 'featureSelections');

        $choices = $this->handler->getChoices($character);

        $this->assertCount(1, $choices);
        $this->assertEquals('Paladin', $choices->first()->sourceName);
        $this->assertEquals(2, $choices->first()->levelGranted);
    }

    /** @test */
    public function it_generates_pending_choice_for_ranger_level_2(): void
    {
        $character = Character::factory()->create();
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $this->ranger->full_slug,
            'level' => 2,
            'is_primary' => true,
        ]);

        $character->load('characterClasses.characterClass', 'featureSelections');

        $choices = $this->handler->getChoices($character);

        $this->assertCount(1, $choices);
        $this->assertEquals('Ranger', $choices->first()->sourceName);
        $this->assertEquals(2, $choices->first()->levelGranted);
    }

    /** @test */
    public function it_does_not_generate_choice_if_already_selected_for_class(): void
    {
        $character = Character::factory()->create();
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $this->fighter->full_slug,
            'level' => 1,
            'is_primary' => true,
        ]);

        // Create existing feature selection (Defense already taken by this Fighter)
        FeatureSelection::factory()->create([
            'character_id' => $character->id,
            'optional_feature_slug' => $this->defense->full_slug,
            'class_slug' => $this->fighter->full_slug,
            'level_acquired' => 1,
        ]);

        $character->load('characterClasses.characterClass', 'featureSelections');

        $choices = $this->handler->getChoices($character);

        // Should have 0 pending choices - Fighter already selected their style
        $this->assertCount(0, $choices);
    }

    /** @test */
    public function it_handles_multiclass_character_with_multiple_fighting_style_sources(): void
    {
        $character = Character::factory()->create();

        // Fighter level 3 (gets fighting style at level 1)
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $this->fighter->full_slug,
            'level' => 3,
            'is_primary' => true,
            'order' => 1,
        ]);

        // Paladin level 2 (gets fighting style at level 2)
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $this->paladin->full_slug,
            'level' => 2,
            'is_primary' => false,
            'order' => 2,
        ]);

        // Fighter already has Archery
        FeatureSelection::factory()->create([
            'character_id' => $character->id,
            'optional_feature_slug' => $this->archery->full_slug,
            'class_slug' => $this->fighter->full_slug,
            'level_acquired' => 1,
        ]);

        $character->load('characterClasses.characterClass', 'featureSelections');

        $choices = $this->handler->getChoices($character);

        // Should have 1 pending choice for Paladin (Fighter already has one selected)
        $this->assertCount(1, $choices);
        $this->assertEquals('Paladin', $choices->first()->sourceName);

        // Archery should be excluded from options
        $optionIds = collect($choices->first()->options)->pluck('id')->all();
        $this->assertNotContains($this->archery->id, $optionIds);
    }

    /** @test */
    public function it_does_not_generate_choice_if_level_requirement_not_met(): void
    {
        $character = Character::factory()->create();
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $this->fighter->full_slug,
            'level' => 0,  // Not yet level 1
            'is_primary' => true,
        ]);

        $character->load('characterClasses.characterClass', 'featureSelections');

        $choices = $this->handler->getChoices($character);

        $this->assertEmpty($choices);
    }

    /** @test */
    public function it_resolves_choice_by_creating_feature_selection_record(): void
    {
        $character = Character::factory()->create();

        // Create a pending choice
        $choice = new PendingChoice(
            id: 'fighting_style|class|'.$this->fighter->full_slug.'|1|fighting_style',
            type: 'fighting_style',
            subtype: null,
            source: 'class',
            sourceName: 'Fighter',
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: [],
            optionsEndpoint: null,
            metadata: []
        );

        // Mock selection
        $selection = ['selected' => [$this->archery->id]];

        $this->handler->resolve($character, $choice, $selection);

        // Verify feature selection was created
        $this->assertDatabaseHas('feature_selections', [
            'character_id' => $character->id,
            'optional_feature_slug' => $this->archery->full_slug,
            'class_slug' => $this->fighter->full_slug,
            'level_acquired' => 1,
        ]);
    }

    /** @test */
    public function it_throws_exception_when_resolving_with_empty_selection(): void
    {
        $this->expectException(InvalidSelectionException::class);
        $this->expectExceptionMessage('Selection cannot be empty');

        $character = Character::factory()->create();

        $choice = new PendingChoice(
            id: 'fighting_style|class|'.$this->fighter->full_slug.'|1|fighting_style',
            type: 'fighting_style',
            subtype: null,
            source: 'class',
            sourceName: 'Fighter',
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: [],
            optionsEndpoint: null,
            metadata: []
        );

        $selection = ['selected' => []];

        $this->handler->resolve($character, $choice, $selection);
    }

    /** @test */
    public function it_indicates_choices_cannot_be_undone(): void
    {
        $character = Character::factory()->create();

        $choice = new PendingChoice(
            id: 'fighting_style|class|'.$this->fighter->full_slug.'|1|fighting_style',
            type: 'fighting_style',
            subtype: null,
            source: 'class',
            sourceName: 'Fighter',
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: [],
            optionsEndpoint: null,
            metadata: []
        );

        $this->assertFalse($this->handler->canUndo($character, $choice));
    }

    /** @test */
    public function it_throws_exception_when_attempting_to_undo(): void
    {
        $this->expectException(ChoiceNotUndoableException::class);
        $this->expectExceptionMessage('Fighting style choices are permanent');

        $character = Character::factory()->create();

        $choice = new PendingChoice(
            id: 'fighting_style|class|'.$this->fighter->full_slug.'|1|fighting_style',
            type: 'fighting_style',
            subtype: null,
            source: 'class',
            sourceName: 'Fighter',
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: [],
            optionsEndpoint: null,
            metadata: []
        );

        $this->handler->undo($character, $choice);
    }
}
