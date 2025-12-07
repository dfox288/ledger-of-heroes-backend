<?php

namespace Tests\Feature\Services\ChoiceHandlers;

use App\DTOs\PendingChoice;
use App\Models\Character;
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
        $this->handler = new EquipmentChoiceHandler;
    }

    #[Test]
    public function resolving_equipment_choice_twice_replaces_instead_of_duplicating(): void
    {
        // Create a character
        $character = Character::factory()->create();

        // Create test items
        $chainMail = Item::factory()->create(['name' => 'Test Chain Mail', 'slug' => 'test-chain-mail-1', 'full_slug' => 'test:test-chain-mail-1']);
        $leatherArmor = Item::factory()->create(['name' => 'Test Leather Armor', 'slug' => 'test-leather-armor-1', 'full_slug' => 'test:test-leather-armor-1']);

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
                        ['full_slug' => $chainMail->full_slug, 'name' => 'Test Chain Mail', 'slug' => 'test-chain-mail-1', 'quantity' => 1],
                    ],
                ],
                [
                    'option' => 'b',
                    'label' => 'leather armor',
                    'items' => [
                        ['full_slug' => $leatherArmor->full_slug, 'name' => 'Test Leather Armor', 'slug' => 'test-leather-armor-1', 'quantity' => 1],
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
        expect($character->equipment->first()->item_slug)->toBe($chainMail->full_slug);

        // Second resolution: change to option 'b' (Leather Armor)
        $this->handler->resolve($character, $choice, ['selected' => 'b']);

        // Verify character now has ONLY Leather Armor (Chain Mail should be replaced)
        $character->refresh();
        expect($character->equipment)->toHaveCount(1)
            ->and($character->equipment->first()->item_slug)->toBe($leatherArmor->full_slug);
    }

    #[Test]
    public function resolving_equipment_choice_with_multiple_items_replaces_all(): void
    {
        $character = Character::factory()->create();

        $sword = Item::factory()->create(['name' => 'Test Longsword', 'slug' => 'test-longsword-2', 'full_slug' => 'test:test-longsword-2']);
        $bow = Item::factory()->create(['name' => 'Test Longbow', 'slug' => 'test-longbow-2', 'full_slug' => 'test:test-longbow-2']);
        $arrows = Item::factory()->create(['name' => 'Test Arrows', 'slug' => 'test-arrow-2', 'full_slug' => 'test:test-arrow-2']);

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
                        ['full_slug' => $sword->full_slug, 'name' => 'Test Longsword', 'slug' => 'test-longsword-2', 'quantity' => 1],
                    ],
                ],
                [
                    'option' => 'b',
                    'label' => 'a longbow and arrows',
                    'items' => [
                        ['full_slug' => $bow->full_slug, 'name' => 'Test Longbow', 'slug' => 'test-longbow-2', 'quantity' => 1],
                        ['full_slug' => $arrows->full_slug, 'name' => 'Test Arrows', 'slug' => 'test-arrow-2', 'quantity' => 20],
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
            ->and($character->equipment->first()->item_slug)->toBe($sword->full_slug);
    }

    #[Test]
    public function resolving_different_choice_groups_does_not_affect_each_other(): void
    {
        $character = Character::factory()->create();

        $armor = Item::factory()->create(['name' => 'Test Chain Mail', 'slug' => 'test-chain-mail-3', 'full_slug' => 'test:test-chain-mail-3']);
        $weapon = Item::factory()->create(['name' => 'Test Longsword', 'slug' => 'test-longsword-3', 'full_slug' => 'test:test-longsword-3']);

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
                        ['full_slug' => $armor->full_slug, 'name' => 'Test Chain Mail', 'slug' => 'test-chain-mail-3', 'quantity' => 1],
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
                        ['full_slug' => $weapon->full_slug, 'name' => 'Test Longsword', 'slug' => 'test-longsword-3', 'quantity' => 1],
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
        expect($itemSlugs)->toContain($armor->full_slug)
            ->and($itemSlugs)->toContain($weapon->full_slug);
    }

    #[Test]
    public function item_selections_filters_to_only_specified_items(): void
    {
        $character = Character::factory()->create();

        // Create multiple items for a category-based choice
        $drum = Item::factory()->create(['name' => 'Drum', 'slug' => 'drum', 'full_slug' => 'phb:drum']);
        $flute = Item::factory()->create(['name' => 'Flute', 'slug' => 'flute', 'full_slug' => 'phb:flute']);
        $lute = Item::factory()->create(['name' => 'Lute', 'slug' => 'lute', 'full_slug' => 'phb:lute']);

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
                        ['full_slug' => $lute->full_slug, 'name' => 'Lute', 'slug' => 'lute', 'quantity' => 1],
                    ],
                ],
                [
                    'option' => 'b',
                    'label' => 'any musical instrument',
                    'items' => [
                        // All instruments available in category
                        ['full_slug' => $drum->full_slug, 'name' => 'Drum', 'slug' => 'drum', 'quantity' => 1],
                        ['full_slug' => $flute->full_slug, 'name' => 'Flute', 'slug' => 'flute', 'quantity' => 1],
                        ['full_slug' => $lute->full_slug, 'name' => 'Lute', 'slug' => 'lute', 'quantity' => 1],
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
        $leatherArmor = Item::factory()->create(['name' => 'Leather Armor', 'slug' => 'leather-armor', 'full_slug' => 'test:leather-armor']);
        $longbow = Item::factory()->create(['name' => 'Longbow', 'slug' => 'longbow', 'full_slug' => 'test:longbow']);
        $arrows = Item::factory()->create(['name' => 'Arrows', 'slug' => 'arrow', 'full_slug' => 'test:arrow']);

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
                        ['full_slug' => $leatherArmor->full_slug, 'name' => 'Leather Armor', 'slug' => 'leather-armor', 'quantity' => 1],
                        ['full_slug' => $longbow->full_slug, 'name' => 'Longbow', 'slug' => 'longbow', 'quantity' => 1],
                        ['full_slug' => $arrows->full_slug, 'name' => 'Arrows', 'slug' => 'arrow', 'quantity' => 20],
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
        expect($itemSlugs)->toContain($leatherArmor->full_slug)
            ->and($itemSlugs)->toContain($longbow->full_slug)
            ->and($itemSlugs)->toContain($arrows->full_slug);

        // Verify arrows have quantity 20
        $arrowEquipment = $character->equipment->where('item_slug', $arrows->full_slug)->first();
        expect($arrowEquipment->quantity)->toBe(20);
    }

    #[Test]
    public function metadata_includes_selected_option(): void
    {
        $character = Character::factory()->create();

        $armor = Item::factory()->create(['name' => 'Chain Mail', 'slug' => 'chain-mail', 'full_slug' => 'test:chain-mail']);

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
                        ['full_slug' => $armor->full_slug, 'name' => 'Chain Mail', 'slug' => 'chain-mail', 'quantity' => 1],
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
}
