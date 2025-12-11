<?php

namespace Tests\Unit\Services\ChoiceHandlers;

use App\DTOs\PendingChoice;
use App\Exceptions\ChoiceNotUndoableException;
use App\Exceptions\InvalidSelectionException;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use App\Models\CharacterFeature;
use App\Models\Feat;
use App\Models\Modifier;
use App\Models\Race;
use App\Services\ChoiceHandlers\HitPointRollChoiceHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
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

    #[Test]
    public function it_returns_hit_points_as_type(): void
    {
        $this->assertEquals('hit_points', $this->handler->getType());
    }

    #[Test]
    public function it_returns_empty_collection_when_character_has_no_classes(): void
    {
        $character = Character::factory()->create();
        $character->setRelation('characterClasses', collect());

        $choices = $this->handler->getChoices($character);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $choices);
        $this->assertEmpty($choices);
    }

    #[Test]
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
            'class_slug' => $class->slug,
            'level' => 1,
            'is_primary' => true,
        ]);
        $pivot->setRelation('characterClass', $class);
        $character->setRelation('characterClasses', collect([$pivot]));

        $choices = $this->handler->getChoices($character);

        $this->assertEmpty($choices);
    }

    #[Test]
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
            'class_slug' => $class->slug,
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

    #[Test]
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
            'class_slug' => $class->slug,
            'level' => 2,
            'is_primary' => true,
        ]);
        $pivot->setRelation('characterClass', $class);
        $character->setRelation('characterClasses', collect([$pivot]));

        $choices = $this->handler->getChoices($character);
        $choice = $choices->first();

        $this->assertCount(3, $choice->options);

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

    #[Test]
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
            'class_slug' => $class->slug,
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

    #[Test]
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
            'class_slug' => $wizard->slug,
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

    #[Test]
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
            'class_slug' => $class->slug,
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

    #[Test]
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
            'class_slug' => $class->slug,
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

    #[Test]
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
            'class_slug' => $class->slug,
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

    #[Test]
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
            'class_slug' => $class->slug,
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

    #[Test]
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
            'class_slug' => $class->slug,
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

    #[Test]
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

    #[Test]
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

    #[Test]
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

    #[Test]
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

    // =====================
    // New HP Tracking Tests
    // =====================

    #[Test]
    public function it_returns_no_choices_when_all_levels_have_hp_resolved(): void
    {
        $class = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'hit_die' => 10,
        ]);

        $character = Character::factory()->create([
            'constitution' => 14,
            'hp_levels_resolved' => [1, 2, 3], // All levels resolved
        ]);

        $pivot = CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $class->slug,
            'level' => 3,
            'is_primary' => true,
        ]);
        $pivot->setRelation('characterClass', $class);
        $character->setRelation('characterClasses', collect([$pivot]));

        $choices = $this->handler->getChoices($character);

        $this->assertEmpty($choices);
    }

    #[Test]
    public function it_returns_choices_only_for_unresolved_levels(): void
    {
        $class = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'hit_die' => 10,
        ]);

        $character = Character::factory()->create([
            'constitution' => 14,
            'hp_levels_resolved' => [1, 2], // Only levels 1 and 2 resolved
        ]);

        $pivot = CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $class->slug,
            'level' => 4,
            'is_primary' => true,
        ]);
        $pivot->setRelation('characterClass', $class);
        $character->setRelation('characterClasses', collect([$pivot]));

        $choices = $this->handler->getChoices($character);

        // Should have choices for levels 3 and 4 (level 1 is auto, levels 2 is resolved)
        $this->assertCount(2, $choices);

        $levels = $choices->pluck('levelGranted')->toArray();
        $this->assertEquals([3, 4], $levels);
    }

    #[Test]
    public function it_marks_level_as_resolved_after_choice(): void
    {
        $class = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'hit_die' => 10,
        ]);

        $character = Character::factory()->create([
            'constitution' => 14,
            'max_hit_points' => 12,
            'current_hit_points' => 12,
            'hp_levels_resolved' => [1], // Only level 1 resolved
        ]);

        $pivot = CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $class->slug,
            'level' => 2,
            'is_primary' => true,
        ]);
        $pivot->setRelation('characterClass', $class);

        $choice = new PendingChoice(
            id: 'hit_points:levelup:'.$character->id.':2:hp',
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

        // Level 2 should now be marked as resolved
        $this->assertEquals([1, 2], $character->hp_levels_resolved);
        $this->assertTrue($character->hasResolvedHpForLevel(2));
    }

    // ==================
    // Manual Roll Tests
    // ==================

    #[Test]
    public function it_resolves_manual_roll_with_valid_roll_result(): void
    {
        $class = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'hit_die' => 10,
        ]);

        $character = Character::factory()->create([
            'constitution' => 14, // +2 modifier
            'max_hit_points' => 12,
            'current_hit_points' => 12,
        ]);

        $pivot = CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $class->slug,
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

        $this->handler->resolve($character, $choice, [
            'selected' => 'manual',
            'roll_result' => 7,
        ]);

        $character->refresh();

        // HP should be increased by exactly 9 (7 roll + 2 CON)
        $this->assertEquals(21, $character->max_hit_points); // 12 + 9
        $this->assertEquals(21, $character->current_hit_points);
    }

    #[Test]
    public function it_throws_exception_when_manual_roll_missing_roll_result(): void
    {
        $this->expectException(InvalidSelectionException::class);
        $this->expectExceptionMessage('roll_result is required');

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
            metadata: [
                'hit_die' => 'd10',
                'con_modifier' => 0,
            ],
        );

        $this->handler->resolve($character, $choice, ['selected' => 'manual']);
    }

    #[Test]
    public function it_throws_exception_when_roll_result_below_1(): void
    {
        $this->expectException(InvalidSelectionException::class);
        $this->expectExceptionMessage('roll_result must be between 1 and 10');

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
            metadata: [
                'hit_die' => 'd10',
                'con_modifier' => 0,
            ],
        );

        $this->handler->resolve($character, $choice, [
            'selected' => 'manual',
            'roll_result' => 0,
        ]);
    }

    #[Test]
    public function it_throws_exception_when_roll_result_above_hit_die(): void
    {
        $this->expectException(InvalidSelectionException::class);
        $this->expectExceptionMessage('roll_result must be between 1 and 6');

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
            metadata: [
                'hit_die' => 'd6',
                'con_modifier' => 0,
            ],
        );

        $this->handler->resolve($character, $choice, [
            'selected' => 'manual',
            'roll_result' => 7,
        ]);
    }

    #[Test]
    public function it_throws_exception_when_roll_result_is_float(): void
    {
        $this->expectException(InvalidSelectionException::class);
        $this->expectExceptionMessage('roll_result must be an integer');

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
            metadata: [
                'hit_die' => 'd10',
                'con_modifier' => 0,
            ],
        );

        $this->handler->resolve($character, $choice, [
            'selected' => 'manual',
            'roll_result' => 7.5,
        ]);
    }

    #[Test]
    public function it_enforces_minimum_1_hp_on_manual_roll_with_negative_con(): void
    {
        $class = CharacterClass::factory()->create([
            'name' => 'Wizard',
            'hit_die' => 6,
        ]);

        $character = Character::factory()->create([
            'constitution' => 3, // -4 modifier
            'max_hit_points' => 2,
            'current_hit_points' => 2,
        ]);

        $pivot = CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $class->slug,
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

        // Roll of 1 with -4 CON = -3, but min is 1
        $this->handler->resolve($character, $choice, [
            'selected' => 'manual',
            'roll_result' => 1,
        ]);

        $character->refresh();

        $this->assertEquals(3, $character->max_hit_points); // 2 + 1 (min)
        $this->assertEquals(3, $character->current_hit_points);
    }

    #[Test]
    public function it_includes_manual_option_in_hp_choices(): void
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
            'class_slug' => $class->slug,
            'level' => 2,
            'is_primary' => true,
        ]);
        $pivot->setRelation('characterClass', $class);
        $character->setRelation('characterClasses', collect([$pivot]));

        $choices = $this->handler->getChoices($character);
        $choice = $choices->first();

        $this->assertCount(3, $choice->options);

        $manualOption = collect($choice->options)->firstWhere('id', 'manual');
        $this->assertNotNull($manualOption);
        $this->assertEquals('Manual Roll', $manualOption['name']);
        $this->assertEquals(1, $manualOption['min_roll']);
        $this->assertEquals(10, $manualOption['max_roll']);
    }

    // =====================
    // Feat HP Bonus Tests (Tough Feat)
    // =====================

    #[Test]
    public function it_adds_feat_hp_bonus_on_level_up(): void
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
            'class_slug' => $class->slug,
            'level' => 2,
            'is_primary' => true,
        ]);
        $pivot->setRelation('characterClass', $class);

        // Create Tough feat with hit_points_per_level modifier
        $toughFeat = Feat::factory()->create([
            'slug' => 'tough',
            'name' => 'Tough',
        ]);

        Modifier::create([
            'reference_type' => Feat::class,
            'reference_id' => $toughFeat->id,
            'modifier_category' => 'hit_points_per_level',
            'value' => 2,
        ]);

        // Grant the Tough feat to the character
        CharacterFeature::create([
            'character_id' => $character->id,
            'feature_type' => Feat::class,
            'feature_id' => $toughFeat->id,
            'feature_slug' => $toughFeat->slug,
            'source' => 'asi_choice',
        ]);

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

        // HP should be increased by:
        // - 6 (average d10) + 2 (CON) = 8 base HP gain
        // - + 2 (Tough feat bonus per level)
        // - Total: 10 HP gained
        $this->assertEquals(22, $character->max_hit_points); // 12 + 10
        $this->assertEquals(22, $character->current_hit_points);
    }

    // =====================
    // Race HP Bonus Tests (Hill Dwarf)
    // =====================

    #[Test]
    public function it_adds_race_hp_bonus_on_level_up(): void
    {
        $class = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'hit_die' => 10,
        ]);

        // Create Hill Dwarf race with HP modifier
        $hillDwarf = Race::factory()->create([
            'slug' => 'dwarf-hill',
            'name' => 'Hill Dwarf',
        ]);

        Modifier::create([
            'reference_type' => Race::class,
            'reference_id' => $hillDwarf->id,
            'modifier_category' => 'hp',
            'value' => 1,
        ]);

        $character = Character::factory()->create([
            'race_slug' => $hillDwarf->slug,
            'constitution' => 14, // +2 modifier
            'max_hit_points' => 13, // Level 1 HP (10 + 2 CON + 1 race)
            'current_hit_points' => 13,
        ]);

        $pivot = CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $class->slug,
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

        // HP should be increased by:
        // - 6 (average d10) + 2 (CON) = 8 base HP gain
        // - + 1 (Hill Dwarf race bonus per level)
        // - Total: 9 HP gained
        $this->assertEquals(22, $character->max_hit_points); // 13 + 9
        $this->assertEquals(22, $character->current_hit_points);
    }

    #[Test]
    public function it_combines_race_and_feat_hp_bonuses_on_level_up(): void
    {
        $class = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'hit_die' => 10,
        ]);

        // Create Hill Dwarf race with HP modifier
        $hillDwarf = Race::factory()->create([
            'slug' => 'dwarf-hill',
            'name' => 'Hill Dwarf',
        ]);

        Modifier::create([
            'reference_type' => Race::class,
            'reference_id' => $hillDwarf->id,
            'modifier_category' => 'hp',
            'value' => 1,
        ]);

        // Create Tough feat with hit_points_per_level modifier
        $toughFeat = Feat::factory()->create([
            'slug' => 'tough',
            'name' => 'Tough',
        ]);

        Modifier::create([
            'reference_type' => Feat::class,
            'reference_id' => $toughFeat->id,
            'modifier_category' => 'hit_points_per_level',
            'value' => 2,
        ]);

        $character = Character::factory()->create([
            'race_slug' => $hillDwarf->slug,
            'constitution' => 14, // +2 modifier
            'max_hit_points' => 13, // Level 1 HP
            'current_hit_points' => 13,
        ]);

        // Grant the Tough feat to the character
        CharacterFeature::create([
            'character_id' => $character->id,
            'feature_type' => Feat::class,
            'feature_id' => $toughFeat->id,
            'feature_slug' => $toughFeat->slug,
            'source' => 'asi_choice',
        ]);

        $pivot = CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $class->slug,
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

        // HP should be increased by:
        // - 6 (average d10) + 2 (CON) = 8 base HP gain
        // - + 1 (Hill Dwarf race bonus per level)
        // - + 2 (Tough feat bonus per level)
        // - Total: 11 HP gained
        $this->assertEquals(24, $character->max_hit_points); // 13 + 11
        $this->assertEquals(24, $character->current_hit_points);
    }

    // =====================
    // Array Format Tests (Issue #481)
    // Form validation normalizes 'selected' to array format
    // =====================

    #[Test]
    public function it_resolves_roll_choice_when_selected_is_array(): void
    {
        $class = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'hit_die' => 10,
        ]);

        $character = Character::factory()->create([
            'constitution' => 14, // +2 modifier
            'max_hit_points' => 12,
            'current_hit_points' => 12,
        ]);

        $pivot = CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $class->slug,
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

        // API sends selected as array (via form validation)
        $this->handler->resolve($character, $choice, ['selected' => ['roll']]);

        $character->refresh();

        // HP should be increased (same as scalar 'roll' test)
        $hpGained = $character->max_hit_points - 12;
        $this->assertGreaterThanOrEqual(3, $hpGained);
        $this->assertLessThanOrEqual(12, $hpGained);
    }

    #[Test]
    public function it_resolves_average_choice_when_selected_is_array(): void
    {
        $class = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'hit_die' => 10,
        ]);

        $character = Character::factory()->create([
            'constitution' => 14, // +2 modifier
            'max_hit_points' => 12,
            'current_hit_points' => 12,
        ]);

        $pivot = CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $class->slug,
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

        // API sends selected as array (via form validation)
        $this->handler->resolve($character, $choice, ['selected' => ['average']]);

        $character->refresh();

        // HP should be increased by exactly 8 (6 average + 2 CON)
        $this->assertEquals(20, $character->max_hit_points);
    }

    #[Test]
    public function it_resolves_manual_choice_when_selected_is_array(): void
    {
        $class = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'hit_die' => 10,
        ]);

        $character = Character::factory()->create([
            'constitution' => 14, // +2 modifier
            'max_hit_points' => 12,
            'current_hit_points' => 12,
        ]);

        $pivot = CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $class->slug,
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

        // API sends selected as array (via form validation)
        $this->handler->resolve($character, $choice, [
            'selected' => ['manual'],
            'roll_result' => 7,
        ]);

        $character->refresh();

        // HP should be increased by exactly 9 (7 roll + 2 CON)
        $this->assertEquals(21, $character->max_hit_points);
    }
}
