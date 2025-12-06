<?php

namespace Tests\Unit\Services\ChoiceHandlers;

use App\DTOs\PendingChoice;
use App\Exceptions\ChoiceNotUndoableException;
use App\Exceptions\InvalidSelectionException;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use App\Services\ChoiceHandlers\HitPointRollChoiceHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HitPointRollChoiceHandlerTest extends TestCase
{
    use RefreshDatabase;

    private HitPointRollChoiceHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = new HitPointRollChoiceHandler;
    }

    /** @test */
    public function it_returns_hit_points_as_type(): void
    {
        $this->assertEquals('hit_points', $this->handler->getType());
    }

    /** @test */
    public function it_returns_empty_collection_when_character_has_no_classes(): void
    {
        $character = Character::factory()->create();
        $character->setRelation('characterClasses', collect());

        $choices = $this->handler->getChoices($character);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $choices);
        $this->assertEmpty($choices);
    }

    /** @test */
    public function it_returns_empty_collection_when_primary_class_is_level_1(): void
    {
        $class = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'hit_die' => 10,
        ]);

        $character = Character::factory()->create([
            'constitution' => 14,
        ]);

        $pivot = CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_id' => $class->id,
            'level' => 1,
            'is_primary' => true,
        ]);
        $pivot->setRelation('characterClass', $class);
        $character->setRelation('characterClasses', collect([$pivot]));

        $choices = $this->handler->getChoices($character);

        $this->assertEmpty($choices);
    }

    /** @test */
    public function it_returns_pending_choice_when_character_levels_to_2(): void
    {
        $class = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'hit_die' => 10,
        ]);

        $character = Character::factory()->create([
            'constitution' => 16, // +3 modifier
        ]);

        $pivot = CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_id' => $class->id,
            'level' => 2,
            'is_primary' => true,
        ]);
        $pivot->setRelation('characterClass', $class);
        $character->setRelation('characterClasses', collect([$pivot]));

        $choices = $this->handler->getChoices($character);

        $this->assertCount(1, $choices);

        $choice = $choices->first();
        $this->assertInstanceOf(PendingChoice::class, $choice);
        $this->assertEquals('hit_points', $choice->type);
        $this->assertEquals('level_up', $choice->source);
        $this->assertEquals('Level 2', $choice->sourceName);
        $this->assertEquals(2, $choice->levelGranted);
        $this->assertTrue($choice->required);
        $this->assertEquals(1, $choice->quantity);
        $this->assertEquals(1, $choice->remaining);
    }

    /** @test */
    public function it_includes_roll_and_average_options(): void
    {
        $class = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'hit_die' => 10,
        ]);

        $character = Character::factory()->create([
            'constitution' => 16, // +3 modifier
        ]);

        $pivot = CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_id' => $class->id,
            'level' => 2,
            'is_primary' => true,
        ]);
        $pivot->setRelation('characterClass', $class);
        $character->setRelation('characterClasses', collect([$pivot]));

        $choices = $this->handler->getChoices($character);
        $choice = $choices->first();

        $this->assertCount(2, $choice->options);

        // Roll option
        $rollOption = collect($choice->options)->firstWhere('id', 'roll');
        $this->assertNotNull($rollOption);
        $this->assertEquals('Roll', $rollOption['name']);
        $this->assertEquals(4, $rollOption['min_result']); // 1 + 3 (CON)
        $this->assertEquals(13, $rollOption['max_result']); // 10 + 3 (CON)

        // Average option
        $averageOption = collect($choice->options)->firstWhere('id', 'average');
        $this->assertNotNull($averageOption);
        $this->assertEquals('Average', $averageOption['name']);
        $this->assertEquals(9, $averageOption['fixed_result']); // 6 + 3 (CON)
    }

    /** @test */
    public function it_includes_metadata_with_hit_die_and_con_modifier(): void
    {
        $class = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter',
            'hit_die' => 10,
        ]);

        $character = Character::factory()->create([
            'constitution' => 16, // +3 modifier
        ]);

        $pivot = CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_id' => $class->id,
            'level' => 2,
            'is_primary' => true,
        ]);
        $pivot->setRelation('characterClass', $class);
        $character->setRelation('characterClasses', collect([$pivot]));

        $choices = $this->handler->getChoices($character);
        $choice = $choices->first();

        $this->assertEquals('d10', $choice->metadata['hit_die']);
        $this->assertEquals(3, $choice->metadata['con_modifier']);
        $this->assertEquals('fighter', $choice->metadata['class_slug']);
    }

    /** @test */
    public function it_handles_different_hit_dice_correctly(): void
    {
        // Test d6 (Wizard)
        $wizard = CharacterClass::factory()->create([
            'name' => 'Wizard',
            'hit_die' => 6,
        ]);

        $character = Character::factory()->create([
            'constitution' => 14, // +2 modifier
        ]);

        $pivot = CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_id' => $wizard->id,
            'level' => 2,
            'is_primary' => true,
        ]);
        $pivot->setRelation('characterClass', $wizard);
        $character->setRelation('characterClasses', collect([$pivot]));

        $choices = $this->handler->getChoices($character);
        $choice = $choices->first();

        $rollOption = collect($choice->options)->firstWhere('id', 'roll');
        $averageOption = collect($choice->options)->firstWhere('id', 'average');

        $this->assertEquals('d6', $choice->metadata['hit_die']);
        $this->assertEquals(3, $rollOption['min_result']); // 1 + 2 (CON)
        $this->assertEquals(8, $rollOption['max_result']); // 6 + 2 (CON)
        $this->assertEquals(6, $averageOption['fixed_result']); // 4 + 2 (CON)
    }

    /** @test */
    public function it_handles_negative_con_modifier_with_minimum_1_hp(): void
    {
        $class = CharacterClass::factory()->create([
            'name' => 'Wizard',
            'hit_die' => 6,
        ]);

        $character = Character::factory()->create([
            'constitution' => 6, // -2 modifier
        ]);

        $pivot = CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_id' => $class->id,
            'level' => 2,
            'is_primary' => true,
        ]);
        $pivot->setRelation('characterClass', $class);
        $character->setRelation('characterClasses', collect([$pivot]));

        $choices = $this->handler->getChoices($character);
        $choice = $choices->first();

        $rollOption = collect($choice->options)->firstWhere('id', 'roll');
        $averageOption = collect($choice->options)->firstWhere('id', 'average');

        $this->assertEquals(-2, $choice->metadata['con_modifier']);
        $this->assertEquals(1, $rollOption['min_result']); // max(1 - 2, 1) = 1
        $this->assertEquals(4, $rollOption['max_result']); // 6 - 2 = 4
        $this->assertEquals(2, $averageOption['fixed_result']); // max(4 - 2, 1) = 2
    }

    /** @test */
    public function it_resolves_roll_choice_with_random_hp(): void
    {
        $class = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'hit_die' => 10,
        ]);

        $character = Character::factory()->create([
            'constitution' => 14, // +2 modifier
            'max_hit_points' => 12, // Level 1 HP
            'current_hit_points' => 12,
        ]);

        $pivot = CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_id' => $class->id,
            'level' => 2,
            'is_primary' => true,
        ]);
        $pivot->setRelation('characterClass', $class);

        $choice = new PendingChoice(
            id: 'hit_points:levelup:'.$character->id.':2',
            type: 'hit_points',
            subtype: null,
            source: 'level_up',
            sourceName: 'Level 2',
            levelGranted: 2,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: [],
            optionsEndpoint: null,
            metadata: [
                'hit_die' => 'd10',
                'con_modifier' => 2,
                'class_slug' => 'fighter',
            ],
        );

        $this->handler->resolve($character, $choice, ['selected' => 'roll']);

        $character->refresh();

        // HP should be increased by at least 3 (1 + 2) and at most 12 (10 + 2)
        $hpGained = $character->max_hit_points - 12;
        $this->assertGreaterThanOrEqual(3, $hpGained);
        $this->assertLessThanOrEqual(12, $hpGained);
        $this->assertEquals($character->max_hit_points, $character->current_hit_points);
    }

    /** @test */
    public function it_resolves_average_choice_with_fixed_hp(): void
    {
        $class = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'hit_die' => 10,
        ]);

        $character = Character::factory()->create([
            'constitution' => 14, // +2 modifier
            'max_hit_points' => 12, // Level 1 HP
            'current_hit_points' => 12,
        ]);

        $pivot = CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_id' => $class->id,
            'level' => 2,
            'is_primary' => true,
        ]);
        $pivot->setRelation('characterClass', $class);

        $choice = new PendingChoice(
            id: 'hit_points:levelup:'.$character->id.':2',
            type: 'hit_points',
            subtype: null,
            source: 'level_up',
            sourceName: 'Level 2',
            levelGranted: 2,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: [],
            optionsEndpoint: null,
            metadata: [
                'hit_die' => 'd10',
                'con_modifier' => 2,
                'class_slug' => 'fighter',
            ],
        );

        $this->handler->resolve($character, $choice, ['selected' => 'average']);

        $character->refresh();

        // HP should be increased by exactly 8 (6 average + 2 CON)
        $this->assertEquals(20, $character->max_hit_points); // 12 + 8
        $this->assertEquals(20, $character->current_hit_points);
    }

    /** @test */
    public function it_enforces_minimum_1_hp_gained_on_roll_with_negative_con(): void
    {
        $class = CharacterClass::factory()->create([
            'name' => 'Wizard',
            'hit_die' => 6,
        ]);

        $character = Character::factory()->create([
            'constitution' => 3, // -4 modifier (extreme case)
            'max_hit_points' => 2, // Level 1 HP (6 - 4 = 2)
            'current_hit_points' => 2,
        ]);

        $pivot = CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_id' => $class->id,
            'level' => 2,
            'is_primary' => true,
        ]);
        $pivot->setRelation('characterClass', $class);

        $choice = new PendingChoice(
            id: 'hit_points:levelup:'.$character->id.':2',
            type: 'hit_points',
            subtype: null,
            source: 'level_up',
            sourceName: 'Level 2',
            levelGranted: 2,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: [],
            optionsEndpoint: null,
            metadata: [
                'hit_die' => 'd6',
                'con_modifier' => -4,
                'class_slug' => 'wizard',
            ],
        );

        $this->handler->resolve($character, $choice, ['selected' => 'roll']);

        $character->refresh();

        // HP should be increased by at least 1
        $hpGained = $character->max_hit_points - 2;
        $this->assertGreaterThanOrEqual(1, $hpGained);
    }

    /** @test */
    public function it_enforces_minimum_1_hp_gained_on_average_with_negative_con(): void
    {
        $class = CharacterClass::factory()->create([
            'name' => 'Wizard',
            'hit_die' => 6,
        ]);

        $character = Character::factory()->create([
            'constitution' => 3, // -4 modifier (extreme case)
            'max_hit_points' => 2, // Level 1 HP
            'current_hit_points' => 2,
        ]);

        $pivot = CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_id' => $class->id,
            'level' => 2,
            'is_primary' => true,
        ]);
        $pivot->setRelation('characterClass', $class);

        $choice = new PendingChoice(
            id: 'hit_points:levelup:'.$character->id.':2',
            type: 'hit_points',
            subtype: null,
            source: 'level_up',
            sourceName: 'Level 2',
            levelGranted: 2,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: [],
            optionsEndpoint: null,
            metadata: [
                'hit_die' => 'd6',
                'con_modifier' => -4,
                'class_slug' => 'wizard',
            ],
        );

        $this->handler->resolve($character, $choice, ['selected' => 'average']);

        $character->refresh();

        // Average is 4, with -4 CON that would be 0, but minimum is 1
        $this->assertEquals(3, $character->max_hit_points); // 2 + 1
        $this->assertEquals(3, $character->current_hit_points);
    }

    /** @test */
    public function it_throws_invalid_selection_exception_when_selected_is_missing(): void
    {
        $this->expectException(InvalidSelectionException::class);

        $character = Character::factory()->create();

        $choice = new PendingChoice(
            id: 'hit_points:levelup:1:2',
            type: 'hit_points',
            subtype: null,
            source: 'level_up',
            sourceName: 'Level 2',
            levelGranted: 2,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: [],
            optionsEndpoint: null,
            metadata: [],
        );

        $this->handler->resolve($character, $choice, []);
    }

    /** @test */
    public function it_throws_invalid_selection_exception_when_selected_is_invalid(): void
    {
        $this->expectException(InvalidSelectionException::class);

        $character = Character::factory()->create();

        $choice = new PendingChoice(
            id: 'hit_points:levelup:1:2',
            type: 'hit_points',
            subtype: null,
            source: 'level_up',
            sourceName: 'Level 2',
            levelGranted: 2,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: [],
            optionsEndpoint: null,
            metadata: [],
        );

        $this->handler->resolve($character, $choice, ['selected' => 'invalid']);
    }

    /** @test */
    public function it_returns_false_for_can_undo(): void
    {
        $character = Character::factory()->create();

        $choice = new PendingChoice(
            id: 'hit_points:levelup:1:2',
            type: 'hit_points',
            subtype: null,
            source: 'level_up',
            sourceName: 'Level 2',
            levelGranted: 2,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: [],
            optionsEndpoint: null,
            metadata: [],
        );

        $this->assertFalse($this->handler->canUndo($character, $choice));
    }

    /** @test */
    public function it_throws_choice_not_undoable_exception_for_undo(): void
    {
        $this->expectException(ChoiceNotUndoableException::class);

        $character = Character::factory()->create();

        $choice = new PendingChoice(
            id: 'hit_points:levelup:1:2',
            type: 'hit_points',
            subtype: null,
            source: 'level_up',
            sourceName: 'Level 2',
            levelGranted: 2,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: [],
            optionsEndpoint: null,
            metadata: [],
        );

        $this->handler->undo($character, $choice);
    }
}
