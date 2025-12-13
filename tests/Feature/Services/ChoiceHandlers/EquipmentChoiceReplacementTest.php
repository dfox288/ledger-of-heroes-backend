<?php

namespace Tests\Feature\Services\ChoiceHandlers;

use App\DTOs\PendingChoice;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use App\Models\EntityChoice;
use App\Models\Item;
use App\Services\ChoiceHandlers\EquipmentChoiceHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('feature-db')]
class EquipmentChoiceReplacementTest extends TestCase
{
    use RefreshDatabase;

    private EquipmentChoiceHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = app(EquipmentChoiceHandler::class);
    }

    #[Test]
    public function resolving_equipment_choice_twice_replaces_instead_of_duplicating(): void
    {
        // Create a character
        $character = Character::factory()->create();

        // Create test items
        $chainMail = Item::factory()->create(['name' => 'Test Chain Mail', 'slug' => 'test:test-chain-mail-1']);
        $leatherArmor = Item::factory()->create(['name' => 'Test Leather Armor', 'slug' => 'test:test-leather-armor-1']);

        // Create a pending choice with two options
        $choice = new PendingChoice(
            id: 'equipment|class|test:fighter|1|equipment_choice_1',
            type: 'equipment',
            subtype: null,
            source: 'class',
            sourceName: 'Fighter',
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: [
                [
                    'option' => 'a',
                    'label' => 'chain mail',
                    'items' => [
                        ['slug' => $chainMail->slug, 'name' => 'Test Chain Mail', 'quantity' => 1],
                    ],
                ],
                [
                    'option' => 'b',
                    'label' => 'leather armor',
                    'items' => [
                        ['slug' => $leatherArmor->slug, 'name' => 'Test Leather Armor', 'quantity' => 1],
                    ],
                ],
            ],
            optionsEndpoint: null,
            metadata: ['choice_group' => 'equipment_choice_1'],
        );

        // First resolution: choose option 'a' (Chain Mail)
        $this->handler->resolve($character, $choice, ['selected' => 'a']);

        // Verify character has Chain Mail
        $character->refresh();
        expect($character->equipment)->toHaveCount(1);
        expect($character->equipment->first()->item_slug)->toBe($chainMail->slug);

        // Second resolution: change to option 'b' (Leather Armor)
        $this->handler->resolve($character, $choice, ['selected' => 'b']);

        // Verify character now has ONLY Leather Armor (Chain Mail should be replaced)
        $character->refresh();
        expect($character->equipment)->toHaveCount(1)
            ->and($character->equipment->first()->item_slug)->toBe($leatherArmor->slug);
    }

    #[Test]
    public function resolving_equipment_choice_with_multiple_items_replaces_all(): void
    {
        $character = Character::factory()->create();

        $sword = Item::factory()->create(['name' => 'Test Longsword', 'slug' => 'test:test-longsword-2']);
        $bow = Item::factory()->create(['name' => 'Test Longbow', 'slug' => 'test:test-longbow-2']);
        $arrows = Item::factory()->create(['name' => 'Test Arrows', 'slug' => 'test:test-arrow-2']);

        $choice = new PendingChoice(
            id: 'equipment|class|test:fighter|1|equipment_choice_2',
            type: 'equipment',
            subtype: null,
            source: 'class',
            sourceName: 'Fighter',
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: [
                [
                    'option' => 'a',
                    'label' => 'a longsword',
                    'items' => [
                        ['slug' => $sword->slug, 'name' => 'Test Longsword', 'quantity' => 1],
                    ],
                ],
                [
                    'option' => 'b',
                    'label' => 'a longbow and arrows',
                    'items' => [
                        ['slug' => $bow->slug, 'name' => 'Test Longbow', 'quantity' => 1],
                        ['slug' => $arrows->slug, 'name' => 'Test Arrows', 'quantity' => 20],
                    ],
                ],
            ],
            optionsEndpoint: null,
            metadata: ['choice_group' => 'equipment_choice_2'],
        );

        // First: choose option 'b' (bow + arrows = 2 items)
        $this->handler->resolve($character, $choice, ['selected' => 'b']);
        $character->refresh();
        expect($character->equipment)->toHaveCount(2);

        // Second: change to option 'a' (sword = 1 item)
        $this->handler->resolve($character, $choice, ['selected' => 'a']);
        $character->refresh();

        // Should have ONLY the sword now
        expect($character->equipment)->toHaveCount(1)
            ->and($character->equipment->first()->item_slug)->toBe($sword->slug);
    }

    #[Test]
    public function resolving_different_choice_groups_does_not_affect_each_other(): void
    {
        $character = Character::factory()->create();

        $armor = Item::factory()->create(['name' => 'Test Chain Mail', 'slug' => 'test:test-chain-mail-3']);
        $weapon = Item::factory()->create(['name' => 'Test Longsword', 'slug' => 'test:test-longsword-3']);

        // Choice 1: Armor choice
        $armorChoice = new PendingChoice(
            id: 'equipment|class|test:fighter|1|equipment_choice_1',
            type: 'equipment',
            subtype: null,
            source: 'class',
            sourceName: 'Fighter',
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: [
                [
                    'option' => 'a',
                    'label' => 'chain mail',
                    'items' => [
                        ['slug' => $armor->slug, 'name' => 'Test Chain Mail', 'quantity' => 1],
                    ],
                ],
            ],
            optionsEndpoint: null,
            metadata: ['choice_group' => 'equipment_choice_1'],
        );

        // Choice 2: Weapon choice (different choice_group)
        $weaponChoice = new PendingChoice(
            id: 'equipment|class|test:fighter|1|equipment_choice_2',
            type: 'equipment',
            subtype: null,
            source: 'class',
            sourceName: 'Fighter',
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: [
                [
                    'option' => 'a',
                    'label' => 'a longsword',
                    'items' => [
                        ['slug' => $weapon->slug, 'name' => 'Test Longsword', 'quantity' => 1],
                    ],
                ],
            ],
            optionsEndpoint: null,
            metadata: ['choice_group' => 'equipment_choice_2'],
        );

        // Resolve armor choice
        $this->handler->resolve($character, $armorChoice, ['selected' => 'a']);
        $character->refresh();
        expect($character->equipment)->toHaveCount(1);

        // Resolve weapon choice
        $this->handler->resolve($character, $weaponChoice, ['selected' => 'a']);
        $character->refresh();

        // Should have BOTH items (different choice groups)
        expect($character->equipment)->toHaveCount(2);
        $itemSlugs = $character->equipment->pluck('item_slug')->toArray();
        expect($itemSlugs)->toContain($armor->slug)
            ->and($itemSlugs)->toContain($weapon->slug);
    }

    #[Test]
    public function item_selections_filters_to_only_specified_items(): void
    {
        $character = Character::factory()->create();

        // Create multiple items for a category-based choice
        $drum = Item::factory()->create(['name' => 'Drum', 'slug' => 'phb:drum']);
        $flute = Item::factory()->create(['name' => 'Flute', 'slug' => 'phb:flute']);
        $lute = Item::factory()->create(['name' => 'Lute', 'slug' => 'phb:lute']);

        // Create a choice with category option containing all instruments
        $choice = new PendingChoice(
            id: 'equipment|class|test:bard|1|choice_3',
            type: 'equipment',
            subtype: null,
            source: 'class',
            sourceName: 'Bard',
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: [
                [
                    'option' => 'a',
                    'label' => 'a lute',
                    'items' => [
                        ['slug' => $lute->slug, 'name' => 'Lute', 'quantity' => 1],
                    ],
                ],
                [
                    'option' => 'b',
                    'label' => 'any musical instrument',
                    'items' => [
                        // All instruments available in category
                        ['slug' => $drum->slug, 'name' => 'Drum', 'quantity' => 1],
                        ['slug' => $flute->slug, 'name' => 'Flute', 'quantity' => 1],
                        ['slug' => $lute->slug, 'name' => 'Lute', 'quantity' => 1],
                    ],
                ],
            ],
            optionsEndpoint: null,
            metadata: ['choice_group' => 'choice_3'],
        );

        // Resolve with item_selections to pick just the Drum from option 'b'
        $this->handler->resolve($character, $choice, [
            'selected' => ['b'],
            'item_selections' => ['b' => ['phb:drum']],
        ]);

        $character->refresh();

        // Should only have the Drum, not all 3 instruments
        expect($character->equipment)->toHaveCount(1);
        expect($character->equipment->first()->item_slug)->toBe('phb:drum');
    }

    #[Test]
    public function resolving_without_item_selections_grants_all_items(): void
    {
        $character = Character::factory()->create();

        // Create multiple items for a fixed option
        $leatherArmor = Item::factory()->create(['name' => 'Leather Armor', 'slug' => 'test:leather-armor']);
        $longbow = Item::factory()->create(['name' => 'Longbow', 'slug' => 'test:longbow']);
        $arrows = Item::factory()->create(['name' => 'Arrows', 'slug' => 'test:arrow']);

        $choice = new PendingChoice(
            id: 'equipment|class|test:fighter|1|equipment_choice_1',
            type: 'equipment',
            subtype: null,
            source: 'class',
            sourceName: 'Fighter',
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: [
                [
                    'option' => 'b',
                    'label' => 'leather armor, longbow, and 20 arrows',
                    'items' => [
                        ['slug' => $leatherArmor->slug, 'name' => 'Leather Armor', 'quantity' => 1],
                        ['slug' => $longbow->slug, 'name' => 'Longbow', 'quantity' => 1],
                        ['slug' => $arrows->slug, 'name' => 'Arrows', 'quantity' => 20],
                    ],
                ],
            ],
            optionsEndpoint: null,
            metadata: ['choice_group' => 'equipment_choice_1'],
        );

        // Resolve without item_selections - should grant all items in the option
        $this->handler->resolve($character, $choice, ['selected' => ['b']]);

        $character->refresh();

        // Should have all 3 items
        expect($character->equipment)->toHaveCount(3);

        $itemSlugs = $character->equipment->pluck('item_slug')->toArray();
        expect($itemSlugs)->toContain($leatherArmor->slug)
            ->and($itemSlugs)->toContain($longbow->slug)
            ->and($itemSlugs)->toContain($arrows->slug);

        // Verify arrows have quantity 20
        $arrowEquipment = $character->equipment->where('item_slug', $arrows->slug)->first();
        expect($arrowEquipment->quantity)->toBe(20);
    }

    #[Test]
    public function metadata_includes_selected_option(): void
    {
        $character = Character::factory()->create();

        $armor = Item::factory()->create(['name' => 'Chain Mail', 'slug' => 'test:chain-mail']);

        $choice = new PendingChoice(
            id: 'equipment|class|test:fighter|1|equipment_choice_1',
            type: 'equipment',
            subtype: null,
            source: 'class',
            sourceName: 'Fighter',
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: [
                [
                    'option' => 'a',
                    'label' => 'chain mail',
                    'items' => [
                        ['slug' => $armor->slug, 'name' => 'Chain Mail', 'quantity' => 1],
                    ],
                ],
            ],
            optionsEndpoint: null,
            metadata: ['choice_group' => 'equipment_choice_1'],
        );

        $this->handler->resolve($character, $choice, ['selected' => ['a']]);

        $character->refresh();
        $equipment = $character->equipment->first();
        $metadata = json_decode($equipment->custom_description, true);

        expect($metadata)->toHaveKey('source', 'class')
            ->and($metadata)->toHaveKey('choice_group', 'equipment_choice_1')
            ->and($metadata)->toHaveKey('selected_option', 'a');
    }

    #[Test]
    public function throws_exception_when_item_selections_contains_invalid_slugs(): void
    {
        $character = Character::factory()->create();

        // Create items for the choice
        $drum = Item::factory()->create(['name' => 'Drum', 'slug' => 'phb:drum']);

        $choice = new PendingChoice(
            id: 'equipment|class|test:bard|1|choice_3',
            type: 'equipment',
            subtype: null,
            source: 'class',
            sourceName: 'Bard',
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: [
                [
                    'option' => 'b',
                    'label' => 'any musical instrument',
                    'items' => [
                        ['slug' => $drum->slug, 'name' => 'Drum', 'quantity' => 1],
                    ],
                ],
            ],
            optionsEndpoint: null,
            metadata: ['choice_group' => 'choice_3'],
        );

        // Try to select an item that doesn't exist in the option
        expect(fn () => $this->handler->resolve($character, $choice, [
            'selected' => ['b'],
            'item_selections' => ['b' => ['phb:nonexistent-item']],
        ]))->toThrow(\App\Exceptions\InvalidSelectionException::class);
    }

    #[Test]
    public function grants_valid_items_even_when_some_slugs_are_invalid(): void
    {
        $character = Character::factory()->create();

        // Create items for the choice
        $drum = Item::factory()->create(['name' => 'Drum', 'slug' => 'phb:drum']);
        $flute = Item::factory()->create(['name' => 'Flute', 'slug' => 'phb:flute']);

        $choice = new PendingChoice(
            id: 'equipment|class|test:bard|1|choice_3',
            type: 'equipment',
            subtype: null,
            source: 'class',
            sourceName: 'Bard',
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: [
                [
                    'option' => 'b',
                    'label' => 'any musical instrument',
                    'items' => [
                        ['slug' => $drum->slug, 'name' => 'Drum', 'quantity' => 1],
                        ['slug' => $flute->slug, 'name' => 'Flute', 'quantity' => 1],
                    ],
                ],
            ],
            optionsEndpoint: null,
            metadata: ['choice_group' => 'choice_3'],
        );

        // Select one valid and one invalid item - should grant the valid one
        $this->handler->resolve($character, $choice, [
            'selected' => ['b'],
            'item_selections' => ['b' => ['phb:drum', 'phb:nonexistent']],
        ]);

        $character->refresh();

        // Should only have the drum (valid item)
        expect($character->equipment)->toHaveCount(1);
        expect($character->equipment->first()->item_slug)->toBe('phb:drum');
    }

    #[Test]
    public function throws_exception_when_category_option_missing_item_selections(): void
    {
        $character = Character::factory()->create();

        // Create multiple items for the choice
        $drum = Item::factory()->create(['name' => 'Drum', 'slug' => 'phb:drum']);
        $flute = Item::factory()->create(['name' => 'Flute', 'slug' => 'phb:flute']);
        $lute = Item::factory()->create(['name' => 'Lute', 'slug' => 'phb:lute']);

        $choice = new PendingChoice(
            id: 'equipment|class|test:bard|1|choice_3',
            type: 'equipment',
            subtype: null,
            source: 'class',
            sourceName: 'Bard',
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: [
                [
                    'option' => 'b',
                    'label' => 'any musical instrument',
                    'items' => [
                        ['slug' => $drum->slug, 'name' => 'Drum', 'quantity' => 1],
                        ['slug' => $flute->slug, 'name' => 'Flute', 'quantity' => 1],
                        ['slug' => $lute->slug, 'name' => 'Lute', 'quantity' => 1],
                    ],
                    'is_category' => true, // This is a category choice - user must pick one
                ],
            ],
            optionsEndpoint: null,
            metadata: ['choice_group' => 'choice_3'],
        );

        // Try to resolve category option without item_selections - should fail
        expect(fn () => $this->handler->resolve($character, $choice, [
            'selected' => ['b'],
            // No item_selections provided
        ]))->toThrow(\App\Exceptions\InvalidSelectionException::class, 'has 3 items to choose from');

        // Verify no equipment was granted
        expect($character->fresh()->equipment)->toHaveCount(0);
    }

    #[Test]
    public function allows_single_item_option_without_item_selections(): void
    {
        $character = Character::factory()->create();

        // Create single item for the choice
        $rapier = Item::factory()->create(['name' => 'Rapier', 'slug' => 'phb:rapier']);

        $choice = new PendingChoice(
            id: 'equipment|class|test:bard|1|choice_1',
            type: 'equipment',
            subtype: null,
            source: 'class',
            sourceName: 'Bard',
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: [
                [
                    'option' => 'a',
                    'label' => 'a rapier',
                    'items' => [
                        ['slug' => $rapier->slug, 'name' => 'Rapier', 'quantity' => 1],
                    ],
                    'is_category' => false, // Fixed item, not a category
                ],
            ],
            optionsEndpoint: null,
            metadata: ['choice_group' => 'choice_1'],
        );

        // Resolve single-item option without item_selections - should succeed
        $this->handler->resolve($character, $choice, [
            'selected' => ['a'],
            // No item_selections needed for single-item option
        ]);

        $character->refresh();

        // Should have the rapier
        expect($character->equipment)->toHaveCount(1);
        expect($character->equipment->first()->item_slug)->toBe('phb:rapier');
    }

    #[Test]
    public function allows_bundle_option_without_item_selections(): void
    {
        $character = Character::factory()->create();

        // Create multiple items for a bundle (not category)
        $bow = Item::factory()->create(['name' => 'Longbow', 'slug' => 'phb:longbow']);
        $arrows = Item::factory()->create(['name' => 'Arrows', 'slug' => 'phb:arrow']);

        $choice = new PendingChoice(
            id: 'equipment|class|test:fighter|1|choice_1',
            type: 'equipment',
            subtype: null,
            source: 'class',
            sourceName: 'Fighter',
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: [
                [
                    'option' => 'b',
                    'label' => 'a longbow and 20 arrows',
                    'items' => [
                        ['slug' => $bow->slug, 'name' => 'Longbow', 'quantity' => 1],
                        ['slug' => $arrows->slug, 'name' => 'Arrows', 'quantity' => 20],
                    ],
                    'is_category' => false, // This is a bundle - you get ALL items
                ],
            ],
            optionsEndpoint: null,
            metadata: ['choice_group' => 'choice_1'],
        );

        // Resolve bundle option without item_selections - should grant ALL items
        $this->handler->resolve($character, $choice, [
            'selected' => ['b'],
            // No item_selections needed for bundle options
        ]);

        $character->refresh();

        // Should have BOTH items from the bundle
        expect($character->equipment)->toHaveCount(2);

        $slugs = $character->equipment->pluck('item_slug')->sort()->values()->toArray();
        expect($slugs)->toBe(['phb:arrow', 'phb:longbow']);
    }

    #[Test]
    public function get_choices_builds_options_from_database_relationships(): void
    {
        // Create items
        $rapier = Item::factory()->create(['name' => 'Rapier', 'slug' => 'phb:rapier']);
        $longsword = Item::factory()->create(['name' => 'Longsword', 'slug' => 'phb:longsword']);
        $bow = Item::factory()->create(['name' => 'Longbow', 'slug' => 'phb:longbow']);
        $arrows = Item::factory()->create(['name' => 'Arrows', 'slug' => 'phb:arrow']);

        // Create a character class with equipment choices
        $class = CharacterClass::factory()->create([
            'name' => 'Test Fighter',
            'slug' => 'test:test-fighter',
        ]);

        // Create equipment choice group 1 with two options (using EntityChoice):
        // Option A: a rapier (single item)
        // Option B: a longsword (single item)
        EntityChoice::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $class->id,
            'choice_type' => 'equipment',
            'choice_group' => 'choice_1',
            'choice_option' => 1,
            'target_type' => 'item',
            'target_slug' => 'phb:rapier',
            'description' => 'a rapier',
            'quantity' => 1,
            'level_granted' => 1,
            'is_required' => true,
        ]);

        EntityChoice::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $class->id,
            'choice_type' => 'equipment',
            'choice_group' => 'choice_1',
            'choice_option' => 2,
            'target_type' => 'item',
            'target_slug' => 'phb:longsword',
            'description' => 'a longsword',
            'quantity' => 1,
            'level_granted' => 1,
            'is_required' => true,
        ]);

        // Create equipment choice group 2 with a bundle option:
        // Option A: a longbow and 20 arrows (bundle - grants all)
        EntityChoice::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $class->id,
            'choice_type' => 'equipment',
            'choice_group' => 'choice_2',
            'choice_option' => 1,
            'target_type' => 'item',
            'target_slug' => 'phb:longbow',
            'description' => 'a longbow and 20 arrows',
            'quantity' => 1,
            'level_granted' => 1,
            'is_required' => true,
        ]);
        EntityChoice::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $class->id,
            'choice_type' => 'equipment',
            'choice_group' => 'choice_2',
            'choice_option' => 1,
            'target_type' => 'item',
            'target_slug' => 'phb:arrow',
            'description' => 'a longbow and 20 arrows',
            'constraints' => ['quantity' => 20],
            'quantity' => 1,
            'level_granted' => 1,
            'is_required' => true,
        ]);

        // Create a level 1 character with this class
        $character = Character::factory()->create();
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $class->slug,
            'level' => 1,
            'is_primary' => true,
        ]);
        $character->load('characterClasses.characterClass');

        // Get choices via the handler
        $choices = $this->handler->getChoices($character);

        // Should have 2 choice groups
        expect($choices)->toHaveCount(2);

        // First choice group: rapier vs longsword
        $choice1 = $choices->first();
        expect($choice1->id)->toContain('choice_1')
            ->and($choice1->options)->toHaveCount(2)
            ->and($choice1->options[0]['option'])->toBe('a')
            ->and($choice1->options[0]['label'])->toBe('a rapier')
            ->and($choice1->options[0]['items'])->toHaveCount(1)
            ->and($choice1->options[0]['items'][0]['slug'])->toBe('phb:rapier')
            ->and($choice1->options[0]['is_category'])->toBeFalse()
            ->and($choice1->options[1]['option'])->toBe('b')
            ->and($choice1->options[1]['label'])->toBe('a longsword')
            ->and($choice1->options[1]['items'][0]['slug'])->toBe('phb:longsword');

        // Second choice group: longbow bundle
        $choice2 = $choices->last();
        expect($choice2->id)->toContain('choice_2')
            ->and($choice2->options)->toHaveCount(1)
            ->and($choice2->options[0]['option'])->toBe('a')
            ->and($choice2->options[0]['items'])->toHaveCount(2)
            ->and($choice2->options[0]['is_category'])->toBeFalse();

        // Verify bundle item quantities
        $bundleItems = collect($choice2->options[0]['items']);
        $bowItem = $bundleItems->firstWhere('slug', 'phb:longbow');
        $arrowItem = $bundleItems->firstWhere('slug', 'phb:arrow');
        expect($bowItem['quantity'])->toBe(1)
            ->and($arrowItem['quantity'])->toBe(20);
    }

    #[Test]
    public function full_flow_with_database_relationships(): void
    {
        // Create items
        $rapier = Item::factory()->create(['name' => 'Rapier', 'slug' => 'test:rapier']);
        $longsword = Item::factory()->create(['name' => 'Longsword', 'slug' => 'test:longsword']);

        // Create class with equipment choices
        $class = CharacterClass::factory()->create([
            'name' => 'Test Warrior',
            'slug' => 'test:test-warrior',
        ]);

        // Option A: rapier, Option B: longsword (using EntityChoice)
        EntityChoice::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $class->id,
            'choice_type' => 'equipment',
            'choice_group' => 'weapon_choice',
            'choice_option' => 1,
            'target_type' => 'item',
            'target_slug' => 'test:rapier',
            'description' => 'a rapier',
            'quantity' => 1,
            'level_granted' => 1,
            'is_required' => true,
        ]);

        EntityChoice::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $class->id,
            'choice_type' => 'equipment',
            'choice_group' => 'weapon_choice',
            'choice_option' => 2,
            'target_type' => 'item',
            'target_slug' => 'test:longsword',
            'description' => 'a longsword',
            'quantity' => 1,
            'level_granted' => 1,
            'is_required' => true,
        ]);

        // Create character
        $character = Character::factory()->create();
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $class->slug,
            'level' => 1,
            'is_primary' => true,
        ]);
        $character->load('characterClasses.characterClass');

        // Get choices
        $choices = $this->handler->getChoices($character);
        expect($choices)->toHaveCount(1);

        $choice = $choices->first();
        expect($choice->remaining)->toBe(1)
            ->and($choice->selected)->toBe([]);

        // Resolve with option 'a' (rapier)
        $this->handler->resolve($character, $choice, ['selected' => ['a']]);
        $character->refresh();

        expect($character->equipment)->toHaveCount(1)
            ->and($character->equipment->first()->item_slug)->toBe('test:rapier');

        // Get choices again - should show remaining=0 and selected=['a']
        $character->load('characterClasses.characterClass');
        $choicesAfter = $this->handler->getChoices($character);
        $choiceAfter = $choicesAfter->first();

        expect($choiceAfter->remaining)->toBe(0)
            ->and($choiceAfter->selected)->toBe(['a']);
    }

    #[Test]
    public function grants_fixed_items_alongside_category_selection(): void
    {
        // Fighter's choice: "a martial weapon and a shield"
        // User picks longsword (from category), shield is fixed
        $character = Character::factory()->create();

        $longsword = Item::factory()->create(['name' => 'Longsword', 'slug' => 'phb:longsword']);
        $greatsword = Item::factory()->create(['name' => 'Greatsword', 'slug' => 'phb:greatsword']);
        $shield = Item::factory()->create(['name' => 'Shield', 'slug' => 'phb:shield']);

        // Option 'a' has both category items (martial weapons) and a fixed item (shield)
        $choice = new PendingChoice(
            id: 'equipment|class|test:fighter|1|choice_2',
            type: 'equipment',
            subtype: null,
            source: 'class',
            sourceName: 'Fighter',
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: [
                [
                    'option' => 'a',
                    'label' => 'a martial weapon and a shield',
                    'items' => [
                        // Category items (user picks one)
                        ['slug' => $longsword->slug, 'name' => 'Longsword', 'quantity' => 1, 'is_fixed' => false],
                        ['slug' => $greatsword->slug, 'name' => 'Greatsword', 'quantity' => 1, 'is_fixed' => false],
                        // Fixed item (always granted with this option)
                        ['slug' => $shield->slug, 'name' => 'Shield', 'quantity' => 1, 'is_fixed' => true],
                    ],
                    'is_category' => true,
                ],
            ],
            optionsEndpoint: null,
            metadata: ['choice_group' => 'choice_2'],
        );

        // User selects option 'a' and picks longsword from the category
        $this->handler->resolve($character, $choice, [
            'selected' => ['a'],
            'item_selections' => ['a' => ['phb:longsword']],
        ]);

        $character->refresh();

        // Should have BOTH: the selected longsword AND the fixed shield
        expect($character->equipment)->toHaveCount(2);

        $slugs = $character->equipment->pluck('item_slug')->sort()->values()->toArray();
        expect($slugs)->toBe(['phb:longsword', 'phb:shield']);
    }

    #[Test]
    public function grants_multiple_fixed_items_with_category_selection(): void
    {
        // Option with category + multiple fixed items (e.g., "any simple weapon, a shield, and 20 arrows")
        $character = Character::factory()->create();

        $dagger = Item::factory()->create(['name' => 'Dagger', 'slug' => 'phb:dagger']);
        $club = Item::factory()->create(['name' => 'Club', 'slug' => 'phb:club']);
        $shield = Item::factory()->create(['name' => 'Shield', 'slug' => 'phb:shield']);
        $arrows = Item::factory()->create(['name' => 'Arrows', 'slug' => 'phb:arrow']);

        $choice = new PendingChoice(
            id: 'equipment|class|test:fighter|1|choice_3',
            type: 'equipment',
            subtype: null,
            source: 'class',
            sourceName: 'Fighter',
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: [
                [
                    'option' => 'a',
                    'label' => 'a simple weapon, a shield, and 20 arrows',
                    'items' => [
                        // Category items
                        ['slug' => $dagger->slug, 'name' => 'Dagger', 'quantity' => 1, 'is_fixed' => false],
                        ['slug' => $club->slug, 'name' => 'Club', 'quantity' => 1, 'is_fixed' => false],
                        // Fixed items
                        ['slug' => $shield->slug, 'name' => 'Shield', 'quantity' => 1, 'is_fixed' => true],
                        ['slug' => $arrows->slug, 'name' => 'Arrows', 'quantity' => 20, 'is_fixed' => true],
                    ],
                    'is_category' => true,
                ],
            ],
            optionsEndpoint: null,
            metadata: ['choice_group' => 'choice_3'],
        );

        // User picks dagger from category
        $this->handler->resolve($character, $choice, [
            'selected' => ['a'],
            'item_selections' => ['a' => ['phb:dagger']],
        ]);

        $character->refresh();

        // Should have: dagger (selected) + shield (fixed) + arrows (fixed)
        expect($character->equipment)->toHaveCount(3);

        $slugs = $character->equipment->pluck('item_slug')->sort()->values()->toArray();
        expect($slugs)->toBe(['phb:arrow', 'phb:dagger', 'phb:shield']);

        // Verify arrows have correct quantity
        $arrowEquipment = $character->equipment->where('item_slug', 'phb:arrow')->first();
        expect($arrowEquipment->quantity)->toBe(20);
    }

    #[Test]
    public function resolving_pack_option_grants_pack_contents(): void
    {
        // When selecting a pack option (e.g., "a diplomat's pack"), the pack contents
        // should be granted, not the pack item itself
        $character = Character::factory()->create();

        // Pack contents
        $chest = Item::factory()->create(['name' => 'Chest', 'slug' => 'phb:chest']);
        $scrollCase = Item::factory()->create(['name' => 'Map or Scroll Case', 'slug' => 'phb:scroll-case']);
        $fineClothes = Item::factory()->create(['name' => 'Fine Clothes', 'slug' => 'phb:fine-clothes']);

        // The pack itself (not granted, only for display)
        $diplomatsPack = Item::factory()->create(['name' => "Diplomat's Pack", 'slug' => 'phb:diplomats-pack']);

        $choice = new PendingChoice(
            id: 'equipment|class|test:bard|1|choice_1',
            type: 'equipment',
            subtype: null,
            source: 'class',
            sourceName: 'Bard',
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: [
                [
                    'option' => 'a',
                    'label' => "a diplomat's pack",
                    'items' => [
                        [
                            'slug' => $diplomatsPack->slug,
                            'name' => "Diplomat's Pack",
                            'quantity' => 1,
                            'is_fixed' => true,
                            'is_pack' => true,
                            'contents' => [
                                ['slug' => $chest->slug, 'name' => 'Chest', 'quantity' => 1],
                                ['slug' => $scrollCase->slug, 'name' => 'Map or Scroll Case', 'quantity' => 2],
                                ['slug' => $fineClothes->slug, 'name' => 'Fine Clothes', 'quantity' => 1],
                            ],
                        ],
                    ],
                    'is_category' => false,
                ],
            ],
            optionsEndpoint: null,
            metadata: ['choice_group' => 'choice_1'],
        );

        // Select the pack option
        $this->handler->resolve($character, $choice, ['selected' => ['a']]);

        $character->refresh();

        // Should have pack CONTENTS, not the pack itself
        expect($character->equipment)->toHaveCount(3);

        $slugs = $character->equipment->pluck('item_slug')->sort()->values()->toArray();
        expect($slugs)->toBe(['phb:chest', 'phb:fine-clothes', 'phb:scroll-case']);

        // Pack itself should NOT be in equipment
        expect($character->equipment->where('item_slug', 'phb:diplomats-pack')->count())->toBe(0);

        // Verify quantities
        $scrollCaseEquipment = $character->equipment->where('item_slug', 'phb:scroll-case')->first();
        expect($scrollCaseEquipment->quantity)->toBe(2);

        // Verify pack items have from_pack metadata
        $chestEquipment = $character->equipment->where('item_slug', 'phb:chest')->first();
        $metadata = json_decode($chestEquipment->custom_description, true);
        expect($metadata)
            ->toHaveKey('from_pack', 'phb:diplomats-pack')
            ->toHaveKey('source', 'class')
            ->toHaveKey('choice_group', 'choice_1');
    }

    #[Test]
    public function pack_items_have_contents_array_for_ui(): void
    {
        // Verify the pack item structure includes contents array for frontend UI
        $character = Character::factory()->create();

        $chest = Item::factory()->create(['name' => 'Chest', 'slug' => 'phb:chest']);
        $diplomatsPack = Item::factory()->create(['name' => "Diplomat's Pack", 'slug' => 'phb:diplomats-pack']);

        $choice = new PendingChoice(
            id: 'equipment|class|test:bard|1|choice_1',
            type: 'equipment',
            subtype: null,
            source: 'class',
            sourceName: 'Bard',
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: [
                [
                    'option' => 'a',
                    'label' => "a diplomat's pack",
                    'items' => [
                        [
                            'slug' => $diplomatsPack->slug,
                            'name' => "Diplomat's Pack",
                            'quantity' => 1,
                            'is_fixed' => true,
                            'is_pack' => true,
                            'contents' => [
                                ['slug' => $chest->slug, 'name' => 'Chest', 'quantity' => 1],
                            ],
                        ],
                    ],
                    'is_category' => false,
                ],
            ],
            optionsEndpoint: null,
            metadata: ['choice_group' => 'choice_1'],
        );

        // Verify structure has is_pack and contents
        $packItem = $choice->options[0]['items'][0];
        expect($packItem)
            ->toHaveKey('is_pack', true)
            ->toHaveKey('contents')
            ->and($packItem['contents'])->toBeArray()
            ->and($packItem['contents'])->toHaveCount(1)
            ->and($packItem['contents'][0])->toHaveKey('slug', 'phb:chest');
    }

    #[Test]
    public function returns_empty_collection_when_gold_mode_selected(): void
    {
        // Create class with equipment choices
        $class = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'test:fighter',
            'starting_wealth_dice' => '5d4',
            'starting_wealth_multiplier' => 10,
        ]);

        $item = Item::factory()->create();
        EntityChoice::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $class->id,
            'choice_type' => 'equipment',
            'choice_group' => 'choice_1',
            'choice_option' => 1,
            'target_type' => 'item',
            'target_slug' => $item->slug,
            'description' => 'chain mail',
            'quantity' => 1,
            'level_granted' => 1,
            'is_required' => true,
        ]);

        // Create character with gold mode selected
        $character = Character::factory()->create();
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $class->slug,
            'level' => 1,
            'is_primary' => true,
        ]);

        // Set gold mode on character
        $character->update(['equipment_mode' => 'gold']);

        $character->load(['characterClasses.characterClass']);

        $choices = $this->handler->getChoices($character);

        // Should return empty - gold mode skips equipment choices
        expect($choices)->toHaveCount(0);
    }

    #[Test]
    public function returns_equipment_choices_when_equipment_mode_selected(): void
    {
        // Create class with equipment choices
        $class = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'test:fighter2',
            'starting_wealth_dice' => '5d4',
            'starting_wealth_multiplier' => 10,
        ]);

        $item = Item::factory()->create();
        EntityChoice::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $class->id,
            'choice_type' => 'equipment',
            'choice_group' => 'choice_1',
            'choice_option' => 1,
            'target_type' => 'item',
            'target_slug' => $item->slug,
            'description' => 'chain mail',
            'quantity' => 1,
            'level_granted' => 1,
            'is_required' => true,
        ]);

        // Create character with equipment mode selected
        $character = Character::factory()->create();
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $class->slug,
            'level' => 1,
            'is_primary' => true,
        ]);

        // Set equipment mode on character
        $character->update(['equipment_mode' => 'equipment']);

        $character->load(['characterClasses.characterClass']);

        $choices = $this->handler->getChoices($character);

        // Should return equipment choices - equipment mode was selected
        expect($choices)->toHaveCount(1);
    }

    #[Test]
    public function category_choice_preserves_parsed_description(): void
    {
        // Create a class with equipment choices - description should be preserved from parsing
        $class = CharacterClass::factory()->create([
            'name' => 'Test Artificer',
            'slug' => 'test:artificer-label-test',
        ]);

        // Create a proficiency type for simple weapons
        $simpleProfType = \App\Models\ProficiencyType::firstOrCreate(
            ['slug' => 'core:simple-weapons'],
            ['name' => 'Simple Weapons', 'category' => 'weapon', 'subcategory' => 'simple']
        );

        // Create a category-based choice with proper description (as importer should store)
        EntityChoice::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $class->id,
            'choice_type' => 'equipment',
            'choice_group' => 'choice_1',
            'choice_option' => 1,
            'target_type' => 'proficiency_type',
            'target_slug' => $simpleProfType->slug,
            'description' => 'any two simple weapons', // Proper description from parser
            'quantity' => 1,
            'level_granted' => 1,
            'is_required' => true,
            'constraints' => ['quantity' => 2],
        ]);

        // Create a character
        $character = Character::factory()->create(['equipment_mode' => 'equipment']);
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $class->slug,
            'level' => 1,
            'is_primary' => true,
        ]);
        $character->load(['characterClasses.characterClass']);

        $choices = $this->handler->getChoices($character);

        expect($choices)->toHaveCount(1);
        $choice = $choices->first();
        expect($choice->options)->toHaveCount(1);

        $option = $choice->options[0];
        // Label should be the stored description from parser
        expect($option['label'])->toBe('any two simple weapons');
        expect($option['select_count'])->toBe(2);
    }

    #[Test]
    public function item_choice_preserves_parsed_description(): void
    {
        // Create a class with equipment choices
        $class = CharacterClass::factory()->create([
            'name' => 'Test Fighter',
            'slug' => 'test:fighter-label-test',
        ]);

        // Create items
        $studdedLeather = Item::factory()->create([
            'name' => 'Studded Leather Armor',
            'slug' => 'test:studded-leather-armor-label',
        ]);
        $scaleMail = Item::factory()->create([
            'name' => 'Scale Mail',
            'slug' => 'test:scale-mail-label',
        ]);

        // Create item-based choices with proper descriptions (as importer should store)
        EntityChoice::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $class->id,
            'choice_type' => 'equipment',
            'choice_group' => 'choice_1',
            'choice_option' => 1,
            'target_type' => 'item',
            'target_slug' => $studdedLeather->slug,
            'description' => 'studded leather armor', // From "(a) studded leather armor"
            'quantity' => 1,
            'level_granted' => 1,
            'is_required' => true,
            'constraints' => ['quantity' => 1],
        ]);

        EntityChoice::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $class->id,
            'choice_type' => 'equipment',
            'choice_group' => 'choice_1',
            'choice_option' => 2,
            'target_type' => 'item',
            'target_slug' => $scaleMail->slug,
            'description' => 'scale mail', // From "(b) scale mail"
            'quantity' => 1,
            'level_granted' => 1,
            'is_required' => true,
            'constraints' => ['quantity' => 1],
        ]);

        // Create a character
        $character = Character::factory()->create(['equipment_mode' => 'equipment']);
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $class->slug,
            'level' => 1,
            'is_primary' => true,
        ]);
        $character->load(['characterClasses.characterClass']);

        $choices = $this->handler->getChoices($character);

        expect($choices)->toHaveCount(1);
        $choice = $choices->first();
        expect($choice->options)->toHaveCount(2);

        // Labels should be the stored descriptions
        $optionA = $choice->options[0];
        $optionB = $choice->options[1];

        expect($optionA['label'])->toBe('studded leather armor');
        expect($optionB['label'])->toBe('scale mail');
    }
}
