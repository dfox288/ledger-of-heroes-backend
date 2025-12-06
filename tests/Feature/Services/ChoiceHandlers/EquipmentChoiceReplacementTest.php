<?php

namespace Tests\Feature\Services\ChoiceHandlers;

use App\DTOs\PendingChoice;
use App\Models\Character;
use App\Models\CharacterEquipment;
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
        $chainMail = Item::factory()->create(['name' => 'Chain Mail', 'slug' => 'chain-mail']);
        $leatherArmor = Item::factory()->create(['name' => 'Leather Armor', 'slug' => 'leather-armor']);

        // Create a pending choice with two options
        $choice = new PendingChoice(
            id: 'equipment:class:1:1:equipment_choice_1',
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
                        ['id' => $chainMail->id, 'name' => 'Chain Mail', 'slug' => 'chain-mail', 'quantity' => 1],
                    ],
                ],
                [
                    'option' => 'b',
                    'label' => 'leather armor',
                    'items' => [
                        ['id' => $leatherArmor->id, 'name' => 'Leather Armor', 'slug' => 'leather-armor', 'quantity' => 1],
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
        expect($character->equipment->first()->item_id)->toBe($chainMail->id);

        // Second resolution: change to option 'b' (Leather Armor)
        $this->handler->resolve($character, $choice, ['selected' => 'b']);

        // Verify character now has ONLY Leather Armor (Chain Mail should be replaced)
        $character->refresh();
        expect($character->equipment)->toHaveCount(1)
            ->and($character->equipment->first()->item_id)->toBe($leatherArmor->id);
    }

    #[Test]
    public function resolving_equipment_choice_with_multiple_items_replaces_all(): void
    {
        $character = Character::factory()->create();

        $sword = Item::factory()->create(['name' => 'Longsword', 'slug' => 'longsword']);
        $bow = Item::factory()->create(['name' => 'Longbow', 'slug' => 'longbow']);
        $arrows = Item::factory()->create(['name' => 'Arrows', 'slug' => 'arrow']);

        $choice = new PendingChoice(
            id: 'equipment:class:1:1:equipment_choice_2',
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
                        ['id' => $sword->id, 'name' => 'Longsword', 'slug' => 'longsword', 'quantity' => 1],
                    ],
                ],
                [
                    'option' => 'b',
                    'label' => 'a longbow and arrows',
                    'items' => [
                        ['id' => $bow->id, 'name' => 'Longbow', 'slug' => 'longbow', 'quantity' => 1],
                        ['id' => $arrows->id, 'name' => 'Arrows', 'slug' => 'arrow', 'quantity' => 20],
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
            ->and($character->equipment->first()->item_id)->toBe($sword->id);
    }

    #[Test]
    public function resolving_different_choice_groups_does_not_affect_each_other(): void
    {
        $character = Character::factory()->create();

        $armor = Item::factory()->create(['name' => 'Chain Mail', 'slug' => 'chain-mail']);
        $weapon = Item::factory()->create(['name' => 'Longsword', 'slug' => 'longsword']);

        // Choice 1: Armor choice
        $armorChoice = new PendingChoice(
            id: 'equipment:class:1:1:equipment_choice_1',
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
                        ['id' => $armor->id, 'name' => 'Chain Mail', 'slug' => 'chain-mail', 'quantity' => 1],
                    ],
                ],
            ],
            optionsEndpoint: null,
            metadata: ['choice_group' => 'equipment_choice_1'],
        );

        // Choice 2: Weapon choice (different choice_group)
        $weaponChoice = new PendingChoice(
            id: 'equipment:class:1:1:equipment_choice_2',
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
                        ['id' => $weapon->id, 'name' => 'Longsword', 'slug' => 'longsword', 'quantity' => 1],
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
        $itemIds = $character->equipment->pluck('item_id')->toArray();
        expect($itemIds)->toContain($armor->id)
            ->and($itemIds)->toContain($weapon->id);
    }
}
