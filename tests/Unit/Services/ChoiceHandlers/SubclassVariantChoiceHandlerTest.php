<?php

declare(strict_types=1);

namespace Tests\Unit\Services\ChoiceHandlers;

use App\DTOs\PendingChoice;
use App\Exceptions\InvalidSelectionException;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use App\Models\ClassFeature;
use App\Services\ChoiceHandlers\SubclassVariantChoiceHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SubclassVariantChoiceHandlerTest extends TestCase
{
    use RefreshDatabase;

    private SubclassVariantChoiceHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = app(SubclassVariantChoiceHandler::class);
    }

    #[Test]
    public function get_type_returns_subclass_variant(): void
    {
        $this->assertSame('subclass_variant', $this->handler->getType());
    }

    #[Test]
    public function returns_no_choice_if_no_subclass_selected(): void
    {
        // Issue #763: No variant choice if no subclass selected yet

        $barbarian = CharacterClass::factory()->create([
            'name' => 'Barbarian',
            'slug' => 'barbarian',
            'parent_class_id' => null,
        ]);

        $character = Character::factory()->create();

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $barbarian->slug,
            'subclass_slug' => null,
            'level' => 6,
            'is_primary' => true,
        ]);

        $choices = $this->handler->getChoices($character);

        $this->assertCount(0, $choices);
    }

    #[Test]
    public function returns_no_choice_if_subclass_has_no_variant_features(): void
    {
        // Issue #763: No variant choice for subclasses without choice_group features

        $barbarian = CharacterClass::factory()->create([
            'name' => 'Barbarian',
            'slug' => 'barbarian',
            'parent_class_id' => null,
        ]);

        $berserker = CharacterClass::factory()->create([
            'name' => 'Path of the Berserker',
            'slug' => 'berserker',
            'parent_class_id' => $barbarian->id,
        ]);

        // Create non-variant features
        ClassFeature::factory()->create([
            'class_id' => $berserker->id,
            'feature_name' => 'Frenzy',
            'level' => 3,
            'choice_group' => null,
        ]);

        $character = Character::factory()->create();

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $barbarian->slug,
            'subclass_slug' => $berserker->slug,
            'level' => 6,
            'is_primary' => true,
        ]);

        $choices = $this->handler->getChoices($character);

        $this->assertCount(0, $choices);
    }

    #[Test]
    public function returns_no_choice_if_character_below_variant_level(): void
    {
        // Issue #763: No variant choice if character hasn't reached the level

        $barbarian = CharacterClass::factory()->create([
            'name' => 'Barbarian',
            'slug' => 'barbarian',
            'parent_class_id' => null,
        ]);

        $totemWarrior = CharacterClass::factory()->create([
            'name' => 'Path of the Totem Warrior',
            'slug' => 'totem-warrior',
            'parent_class_id' => $barbarian->id,
        ]);

        // Create L6 variant features
        ClassFeature::factory()->create([
            'class_id' => $totemWarrior->id,
            'feature_name' => 'Aspect of the Bear (Path of the Totem Warrior)',
            'level' => 6,
            'choice_group' => 'totem_aspect',
        ]);

        $character = Character::factory()->create();

        // Character is only level 5
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $barbarian->slug,
            'subclass_slug' => $totemWarrior->slug,
            'subclass_choices' => ['totem_spirit' => 'bear'],
            'level' => 5,
            'is_primary' => true,
        ]);

        $choices = $this->handler->getChoices($character);

        $this->assertCount(0, $choices);
    }

    #[Test]
    public function returns_no_choice_if_variant_already_selected(): void
    {
        // Issue #763: No choice if this choice_group is already in subclass_choices

        $barbarian = CharacterClass::factory()->create([
            'name' => 'Barbarian',
            'slug' => 'barbarian',
            'parent_class_id' => null,
        ]);

        $totemWarrior = CharacterClass::factory()->create([
            'name' => 'Path of the Totem Warrior',
            'slug' => 'totem-warrior',
            'parent_class_id' => $barbarian->id,
        ]);

        ClassFeature::factory()->create([
            'class_id' => $totemWarrior->id,
            'feature_name' => 'Aspect of the Bear (Path of the Totem Warrior)',
            'level' => 6,
            'choice_group' => 'totem_aspect',
        ]);

        $character = Character::factory()->create();

        // Already has totem_aspect selected
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $barbarian->slug,
            'subclass_slug' => $totemWarrior->slug,
            'subclass_choices' => ['totem_spirit' => 'bear', 'totem_aspect' => 'eagle'],
            'level' => 6,
            'is_primary' => true,
        ]);

        $choices = $this->handler->getChoices($character);

        $this->assertCount(0, $choices);
    }

    #[Test]
    public function returns_choice_for_pending_variant_at_current_level(): void
    {
        // Issue #763: Return choice when character reaches level with unselected variant

        $barbarian = CharacterClass::factory()->create([
            'name' => 'Barbarian',
            'slug' => 'barbarian',
            'parent_class_id' => null,
        ]);

        $totemWarrior = CharacterClass::factory()->create([
            'name' => 'Path of the Totem Warrior',
            'slug' => 'totem-warrior',
            'parent_class_id' => $barbarian->id,
        ]);

        // Create L3 variant features (to establish subclass_level = 3)
        ClassFeature::factory()->create([
            'class_id' => $totemWarrior->id,
            'feature_name' => 'Bear (Path of the Totem Warrior)',
            'level' => 3,
            'choice_group' => 'totem_spirit',
        ]);

        // Create L6 variant features (the ones we're testing)
        ClassFeature::factory()->create([
            'class_id' => $totemWarrior->id,
            'feature_name' => 'Aspect of the Bear (Path of the Totem Warrior)',
            'level' => 6,
            'choice_group' => 'totem_aspect',
        ]);

        ClassFeature::factory()->create([
            'class_id' => $totemWarrior->id,
            'feature_name' => 'Aspect of the Eagle (Path of the Totem Warrior)',
            'level' => 6,
            'choice_group' => 'totem_aspect',
        ]);

        $character = Character::factory()->create();

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $barbarian->slug,
            'subclass_slug' => $totemWarrior->slug,
            'subclass_choices' => ['totem_spirit' => 'bear'],
            'level' => 6,
            'is_primary' => true,
        ]);

        $character->load('characterClasses');

        $choices = $this->handler->getChoices($character);

        $this->assertCount(1, $choices);

        $choice = $choices->first();
        $this->assertInstanceOf(PendingChoice::class, $choice);
        $this->assertSame('subclass_variant', $choice->type);
        $this->assertSame('subclass', $choice->source);
        $this->assertSame('Path of the Totem Warrior', $choice->sourceName);
        $this->assertSame(6, $choice->levelGranted);
        $this->assertTrue($choice->required);
        $this->assertCount(2, $choice->options);

        // Options should be the variant choices
        $optionValues = collect($choice->options)->pluck('value')->sort()->values()->all();
        $this->assertEquals(['aspect of the bear', 'aspect of the eagle'], $optionValues);
    }

    #[Test]
    public function resolve_merges_variant_choice_into_subclass_choices(): void
    {
        // Issue #763: Resolve should MERGE, not replace, subclass_choices

        $barbarian = CharacterClass::factory()->create([
            'name' => 'Barbarian',
            'slug' => 'barbarian',
            'parent_class_id' => null,
        ]);

        $totemWarrior = CharacterClass::factory()->create([
            'name' => 'Path of the Totem Warrior',
            'slug' => 'totem-warrior',
            'parent_class_id' => $barbarian->id,
        ]);

        ClassFeature::factory()->create([
            'class_id' => $totemWarrior->id,
            'feature_name' => 'Aspect of the Bear (Path of the Totem Warrior)',
            'level' => 6,
            'choice_group' => 'totem_aspect',
        ]);

        ClassFeature::factory()->create([
            'class_id' => $totemWarrior->id,
            'feature_name' => 'Aspect of the Eagle (Path of the Totem Warrior)',
            'level' => 6,
            'choice_group' => 'totem_aspect',
        ]);

        $character = Character::factory()->create();

        $pivot = CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $barbarian->slug,
            'subclass_slug' => $totemWarrior->slug,
            'subclass_choices' => ['totem_spirit' => 'bear'],
            'level' => 6,
            'is_primary' => true,
        ]);

        $choice = new PendingChoice(
            id: "subclass_variant|subclass|{$totemWarrior->slug}|6|totem_aspect",
            type: 'subclass_variant',
            subtype: 'totem_aspect',
            source: 'subclass',
            sourceName: 'Path of the Totem Warrior',
            levelGranted: 6,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: [],
            optionsEndpoint: null,
            metadata: [
                'class_slug' => $barbarian->slug,
                'subclass_slug' => $totemWarrior->slug,
                'choice_group' => 'totem_aspect',
            ],
        );

        $this->handler->resolve($character, $choice, [
            'selected' => ['aspect of the eagle'],
        ]);

        $pivot->refresh();
        $this->assertEquals([
            'totem_spirit' => 'bear',
            'totem_aspect' => 'aspect of the eagle',
        ], $pivot->subclass_choices);
    }

    #[Test]
    public function resolve_throws_exception_for_invalid_variant_value(): void
    {
        // Issue #763: Should reject invalid variant values

        $barbarian = CharacterClass::factory()->create([
            'name' => 'Barbarian',
            'slug' => 'barbarian',
            'parent_class_id' => null,
        ]);

        $totemWarrior = CharacterClass::factory()->create([
            'name' => 'Path of the Totem Warrior',
            'slug' => 'totem-warrior',
            'parent_class_id' => $barbarian->id,
        ]);

        ClassFeature::factory()->create([
            'class_id' => $totemWarrior->id,
            'feature_name' => 'Aspect of the Bear (Path of the Totem Warrior)',
            'level' => 6,
            'choice_group' => 'totem_aspect',
        ]);

        $character = Character::factory()->create();

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $barbarian->slug,
            'subclass_slug' => $totemWarrior->slug,
            'subclass_choices' => ['totem_spirit' => 'bear'],
            'level' => 6,
            'is_primary' => true,
        ]);

        $choice = new PendingChoice(
            id: "subclass_variant|subclass|{$totemWarrior->slug}|6|totem_aspect",
            type: 'subclass_variant',
            subtype: 'totem_aspect',
            source: 'subclass',
            sourceName: 'Path of the Totem Warrior',
            levelGranted: 6,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: [],
            optionsEndpoint: null,
            metadata: [
                'class_slug' => $barbarian->slug,
                'subclass_slug' => $totemWarrior->slug,
                'choice_group' => 'totem_aspect',
            ],
        );

        $this->expectException(InvalidSelectionException::class);
        $this->expectExceptionMessage("Invalid totem_aspect value 'aspect of the dragon'");

        $this->handler->resolve($character, $choice, [
            'selected' => ['aspect of the dragon'],  // Invalid
        ]);
    }

    #[Test]
    public function can_undo_returns_true_at_granted_level(): void
    {
        // Issue #763: Can undo if still at the level the choice was made

        $barbarian = CharacterClass::factory()->create([
            'name' => 'Barbarian',
            'slug' => 'barbarian',
            'parent_class_id' => null,
        ]);

        $totemWarrior = CharacterClass::factory()->create([
            'name' => 'Path of the Totem Warrior',
            'slug' => 'totem-warrior',
            'parent_class_id' => $barbarian->id,
        ]);

        $character = Character::factory()->create();

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $barbarian->slug,
            'subclass_slug' => $totemWarrior->slug,
            'subclass_choices' => ['totem_spirit' => 'bear', 'totem_aspect' => 'eagle'],
            'level' => 6,
            'is_primary' => true,
        ]);

        $choice = new PendingChoice(
            id: "subclass_variant|subclass|{$totemWarrior->slug}|6|totem_aspect",
            type: 'subclass_variant',
            subtype: 'totem_aspect',
            source: 'subclass',
            sourceName: 'Path of the Totem Warrior',
            levelGranted: 6,
            required: true,
            quantity: 1,
            remaining: 0,
            selected: ['eagle'],
            options: [],
            optionsEndpoint: null,
            metadata: [
                'class_slug' => $barbarian->slug,
                'subclass_slug' => $totemWarrior->slug,
                'choice_group' => 'totem_aspect',
            ],
        );

        $this->assertTrue($this->handler->canUndo($character, $choice));
    }

    #[Test]
    public function can_undo_returns_false_after_leveling_up(): void
    {
        // Issue #763: Cannot undo after leveling past the choice level

        $barbarian = CharacterClass::factory()->create([
            'name' => 'Barbarian',
            'slug' => 'barbarian',
            'parent_class_id' => null,
        ]);

        $totemWarrior = CharacterClass::factory()->create([
            'name' => 'Path of the Totem Warrior',
            'slug' => 'totem-warrior',
            'parent_class_id' => $barbarian->id,
        ]);

        $character = Character::factory()->create();

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $barbarian->slug,
            'subclass_slug' => $totemWarrior->slug,
            'subclass_choices' => ['totem_spirit' => 'bear', 'totem_aspect' => 'eagle'],
            'level' => 7,  // Leveled past 6
            'is_primary' => true,
        ]);

        $choice = new PendingChoice(
            id: "subclass_variant|subclass|{$totemWarrior->slug}|6|totem_aspect",
            type: 'subclass_variant',
            subtype: 'totem_aspect',
            source: 'subclass',
            sourceName: 'Path of the Totem Warrior',
            levelGranted: 6,
            required: true,
            quantity: 1,
            remaining: 0,
            selected: ['eagle'],
            options: [],
            optionsEndpoint: null,
            metadata: [
                'class_slug' => $barbarian->slug,
                'subclass_slug' => $totemWarrior->slug,
                'choice_group' => 'totem_aspect',
            ],
        );

        $this->assertFalse($this->handler->canUndo($character, $choice));
    }

    #[Test]
    public function undo_removes_variant_choice_from_subclass_choices(): void
    {
        // Issue #763: Undo should remove only the specific choice_group

        $barbarian = CharacterClass::factory()->create([
            'name' => 'Barbarian',
            'slug' => 'barbarian',
            'parent_class_id' => null,
        ]);

        $totemWarrior = CharacterClass::factory()->create([
            'name' => 'Path of the Totem Warrior',
            'slug' => 'totem-warrior',
            'parent_class_id' => $barbarian->id,
        ]);

        $character = Character::factory()->create();

        $pivot = CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $barbarian->slug,
            'subclass_slug' => $totemWarrior->slug,
            'subclass_choices' => ['totem_spirit' => 'bear', 'totem_aspect' => 'eagle'],
            'level' => 6,
            'is_primary' => true,
        ]);

        $choice = new PendingChoice(
            id: "subclass_variant|subclass|{$totemWarrior->slug}|6|totem_aspect",
            type: 'subclass_variant',
            subtype: 'totem_aspect',
            source: 'subclass',
            sourceName: 'Path of the Totem Warrior',
            levelGranted: 6,
            required: true,
            quantity: 1,
            remaining: 0,
            selected: ['eagle'],
            options: [],
            optionsEndpoint: null,
            metadata: [
                'class_slug' => $barbarian->slug,
                'subclass_slug' => $totemWarrior->slug,
                'choice_group' => 'totem_aspect',
            ],
        );

        $this->handler->undo($character, $choice);

        $pivot->refresh();
        // totem_spirit should still be there, totem_aspect removed
        $this->assertEquals(['totem_spirit' => 'bear'], $pivot->subclass_choices);
    }

    #[Test]
    public function returns_multiple_choices_for_multiple_pending_variants(): void
    {
        // Issue #763: If character is at L14 with both L6 and L14 variants pending

        $barbarian = CharacterClass::factory()->create([
            'name' => 'Barbarian',
            'slug' => 'barbarian',
            'parent_class_id' => null,
        ]);

        $totemWarrior = CharacterClass::factory()->create([
            'name' => 'Path of the Totem Warrior',
            'slug' => 'totem-warrior',
            'parent_class_id' => $barbarian->id,
        ]);

        // L3 variant features (to establish subclass_level = 3)
        ClassFeature::factory()->create([
            'class_id' => $totemWarrior->id,
            'feature_name' => 'Bear (Path of the Totem Warrior)',
            'level' => 3,
            'choice_group' => 'totem_spirit',
        ]);

        // L6 variant features
        ClassFeature::factory()->create([
            'class_id' => $totemWarrior->id,
            'feature_name' => 'Aspect of the Bear (Path of the Totem Warrior)',
            'level' => 6,
            'choice_group' => 'totem_aspect',
        ]);

        // L14 variant features
        ClassFeature::factory()->create([
            'class_id' => $totemWarrior->id,
            'feature_name' => 'Bear (Path of the Totem Warrior)',
            'level' => 14,
            'choice_group' => 'totem_attunement',
        ]);

        $character = Character::factory()->create();

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $barbarian->slug,
            'subclass_slug' => $totemWarrior->slug,
            'subclass_choices' => ['totem_spirit' => 'bear'],  // Only L3 done
            'level' => 14,
            'is_primary' => true,
        ]);

        $character->load('characterClasses');

        $choices = $this->handler->getChoices($character);

        // Should have 2 choices: totem_aspect (L6) and totem_attunement (L14)
        $this->assertCount(2, $choices);

        $choiceGroups = $choices->pluck('subtype')->sort()->values()->all();
        $this->assertEquals(['totem_aspect', 'totem_attunement'], $choiceGroups);
    }
}
