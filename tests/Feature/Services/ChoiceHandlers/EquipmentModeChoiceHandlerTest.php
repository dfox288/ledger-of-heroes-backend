<?php

namespace Tests\Feature\Services\ChoiceHandlers;

use App\DTOs\PendingChoice;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use App\Models\CharacterEquipment;
use App\Models\EntityItem;
use App\Models\EquipmentChoiceItem;
use App\Models\Item;
use App\Services\ChoiceHandlers\EquipmentModeChoiceHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('feature-db')]
class EquipmentModeChoiceHandlerTest extends TestCase
{
    use RefreshDatabase;

    private EquipmentModeChoiceHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = app(EquipmentModeChoiceHandler::class);
    }

    #[Test]
    public function returns_correct_type(): void
    {
        expect($this->handler->getType())->toBe('equipment_mode');
    }

    #[Test]
    public function returns_empty_collection_for_level_2_plus_character(): void
    {
        $class = CharacterClass::factory()->create([
            'starting_wealth_dice' => '5d4',
            'starting_wealth_multiplier' => 10,
        ]);

        $character = Character::factory()->create();
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $class->slug,
            'level' => 2,
            'is_primary' => true,
        ]);
        $character->load('characterClasses.characterClass');

        $choices = $this->handler->getChoices($character);

        expect($choices)->toHaveCount(0);
    }

    #[Test]
    public function returns_empty_collection_when_class_has_no_starting_wealth(): void
    {
        $class = CharacterClass::factory()->create([
            'starting_wealth_dice' => null,
            'starting_wealth_multiplier' => null,
        ]);

        $character = Character::factory()->create();
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $class->slug,
            'level' => 1,
            'is_primary' => true,
        ]);
        $character->load('characterClasses.characterClass');

        $choices = $this->handler->getChoices($character);

        expect($choices)->toHaveCount(0);
    }

    #[Test]
    public function returns_equipment_mode_choice_for_level_1_character_with_starting_wealth(): void
    {
        $class = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'phb:fighter',
            'starting_wealth_dice' => '5d4',
            'starting_wealth_multiplier' => 10,
        ]);

        // Create equipment choice so the choice appears
        $item = Item::factory()->create();
        $entityItem = EntityItem::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $class->id,
            'is_choice' => true,
            'choice_group' => 'choice_1',
            'choice_option' => 1,
            'description' => 'chain mail',
            'quantity' => 1,
        ]);
        EquipmentChoiceItem::create([
            'entity_item_id' => $entityItem->id,
            'item_id' => $item->id,
            'quantity' => 1,
            'sort_order' => 0,
        ]);

        $character = Character::factory()->create();
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $class->slug,
            'level' => 1,
            'is_primary' => true,
        ]);
        $character->load('characterClasses.characterClass');

        $choices = $this->handler->getChoices($character);

        expect($choices)->toHaveCount(1);

        $choice = $choices->first();
        expect($choice)
            ->toBeInstanceOf(PendingChoice::class)
            ->and($choice->type)->toBe('equipment_mode')
            ->and($choice->source)->toBe('class')
            ->and($choice->sourceName)->toBe('Fighter')
            ->and($choice->levelGranted)->toBe(1)
            ->and($choice->required)->toBeTrue()
            ->and($choice->quantity)->toBe(1)
            ->and($choice->remaining)->toBe(1)
            ->and($choice->options)->toHaveCount(2);

        // Check options
        expect($choice->options[0]['value'])->toBe('equipment')
            ->and($choice->options[1]['value'])->toBe('gold');

        // Check metadata contains starting_wealth
        expect($choice->metadata)->toHaveKey('starting_wealth')
            ->and($choice->metadata['starting_wealth'])->toHaveKey('dice', '5d4')
            ->and($choice->metadata['starting_wealth'])->toHaveKey('multiplier', 10)
            ->and($choice->metadata['starting_wealth'])->toHaveKey('average', 125)
            ->and($choice->metadata['starting_wealth'])->toHaveKey('formula', '5d4 × 10 gp');
    }

    #[Test]
    public function resolving_with_equipment_sets_equipment_mode_column(): void
    {
        $class = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'phb:fighter',
            'starting_wealth_dice' => '5d4',
            'starting_wealth_multiplier' => 10,
        ]);

        $character = Character::factory()->create();
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $class->slug,
            'level' => 1,
            'is_primary' => true,
        ]);

        $choice = new PendingChoice(
            id: 'equipment_mode|class|phb:fighter|1|starting_equipment',
            type: 'equipment_mode',
            subtype: null,
            source: 'class',
            sourceName: 'Fighter',
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: [
                ['value' => 'equipment', 'label' => 'Take Starting Equipment'],
                ['value' => 'gold', 'label' => 'Take Starting Gold'],
            ],
            optionsEndpoint: null,
            metadata: ['starting_wealth' => ['dice' => '5d4', 'multiplier' => 10, 'average' => 125]],
        );

        $this->handler->resolve($character, $choice, ['selected' => ['equipment']]);

        // Check equipment_mode column was set
        $character->refresh();
        expect($character->equipment_mode)->toBe('equipment');
    }

    #[Test]
    public function resolving_with_gold_adds_gold_to_inventory(): void
    {
        // Create gold item
        Item::factory()->create([
            'name' => 'Gold (gp)',
            'slug' => 'gold-gp',
            'slug' => 'phb:gold-gp',
        ]);

        $class = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'phb:fighter',
            'starting_wealth_dice' => '5d4',
            'starting_wealth_multiplier' => 10,
        ]);

        $character = Character::factory()->create();
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $class->slug,
            'level' => 1,
            'is_primary' => true,
        ]);

        $choice = new PendingChoice(
            id: 'equipment_mode|class|phb:fighter|1|starting_equipment',
            type: 'equipment_mode',
            subtype: null,
            source: 'class',
            sourceName: 'Fighter',
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: [
                ['value' => 'equipment', 'label' => 'Take Starting Equipment'],
                ['value' => 'gold', 'label' => 'Take Starting Gold'],
            ],
            optionsEndpoint: null,
            metadata: ['starting_wealth' => ['dice' => '5d4', 'multiplier' => 10, 'average' => 125]],
        );

        $this->handler->resolve($character, $choice, ['selected' => ['gold'], 'gold_amount' => 130]);

        $character->refresh();

        // Check gold was added
        $goldEquipment = $character->equipment->where('item_slug', 'phb:gold-gp')->first();
        expect($goldEquipment)->not->toBeNull()
            ->and($goldEquipment->quantity)->toBe(130);

        // Check equipment_mode column was set
        expect($character->equipment_mode)->toBe('gold');
    }

    #[Test]
    public function resolving_with_gold_uses_average_when_gold_amount_not_provided(): void
    {
        Item::factory()->create([
            'name' => 'Gold (gp)',
            'slug' => 'gold-gp',
            'slug' => 'phb:gold-gp',
        ]);

        $class = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'phb:fighter',
            'starting_wealth_dice' => '5d4',
            'starting_wealth_multiplier' => 10,
        ]);

        $character = Character::factory()->create();
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $class->slug,
            'level' => 1,
            'is_primary' => true,
        ]);

        $choice = new PendingChoice(
            id: 'equipment_mode|class|phb:fighter|1|starting_equipment',
            type: 'equipment_mode',
            subtype: null,
            source: 'class',
            sourceName: 'Fighter',
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: [
                ['value' => 'equipment', 'label' => 'Take Starting Equipment'],
                ['value' => 'gold', 'label' => 'Take Starting Gold'],
            ],
            optionsEndpoint: null,
            metadata: ['starting_wealth' => ['dice' => '5d4', 'multiplier' => 10, 'average' => 125]],
        );

        // No gold_amount provided - should use average from metadata
        $this->handler->resolve($character, $choice, ['selected' => ['gold']]);

        $character->refresh();

        $goldEquipment = $character->equipment->where('item_slug', 'phb:gold-gp')->first();
        expect($goldEquipment->quantity)->toBe(125);
    }

    #[Test]
    public function choice_shows_resolved_when_already_selected(): void
    {
        $class = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'phb:fighter',
            'starting_wealth_dice' => '5d4',
            'starting_wealth_multiplier' => 10,
        ]);

        // Create equipment choice
        $item = Item::factory()->create();
        $entityItem = EntityItem::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $class->id,
            'is_choice' => true,
            'choice_group' => 'choice_1',
            'choice_option' => 1,
            'description' => 'chain mail',
            'quantity' => 1,
        ]);
        EquipmentChoiceItem::create([
            'entity_item_id' => $entityItem->id,
            'item_id' => $item->id,
            'quantity' => 1,
            'sort_order' => 0,
        ]);

        $character = Character::factory()->create();
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $class->slug,
            'level' => 1,
            'is_primary' => true,
        ]);

        // Set equipment_mode on character
        $character->update(['equipment_mode' => 'equipment']);

        $character->load(['characterClasses.characterClass']);

        $choices = $this->handler->getChoices($character);

        expect($choices)->toHaveCount(1);

        $choice = $choices->first();
        expect($choice->remaining)->toBe(0)
            ->and($choice->selected)->toBe(['equipment']);
    }

    #[Test]
    public function choice_shows_gold_selected_when_gold_mode_was_chosen(): void
    {
        $class = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'phb:fighter',
            'starting_wealth_dice' => '5d4',
            'starting_wealth_multiplier' => 10,
        ]);

        // Create equipment choice
        $item = Item::factory()->create();
        $entityItem = EntityItem::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $class->id,
            'is_choice' => true,
            'choice_group' => 'choice_1',
            'choice_option' => 1,
            'description' => 'chain mail',
            'quantity' => 1,
        ]);
        EquipmentChoiceItem::create([
            'entity_item_id' => $entityItem->id,
            'item_id' => $item->id,
            'quantity' => 1,
            'sort_order' => 0,
        ]);

        $character = Character::factory()->create(['equipment_mode' => 'gold']);
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
        expect($choice->remaining)->toBe(0)
            ->and($choice->selected)->toBe(['gold']);
    }

    #[Test]
    public function choice_metadata_includes_gold_amount_when_gold_mode_was_chosen(): void
    {
        Item::factory()->create([
            'name' => 'Gold (gp)',
            'slug' => 'gold-gp',
            'slug' => 'phb:gold-gp',
        ]);

        $class = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'phb:fighter',
            'starting_wealth_dice' => '5d4',
            'starting_wealth_multiplier' => 10,
        ]);

        // Create equipment choice
        $item = Item::factory()->create();
        $entityItem = EntityItem::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $class->id,
            'is_choice' => true,
            'choice_group' => 'choice_1',
            'choice_option' => 1,
            'description' => 'chain mail',
            'quantity' => 1,
        ]);
        EquipmentChoiceItem::create([
            'entity_item_id' => $entityItem->id,
            'item_id' => $item->id,
            'quantity' => 1,
            'sort_order' => 0,
        ]);

        $character = Character::factory()->create(['equipment_mode' => 'gold']);
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $class->slug,
            'level' => 1,
            'is_primary' => true,
        ]);

        // Add gold to equipment (simulating what resolve() does)
        CharacterEquipment::create([
            'character_id' => $character->id,
            'item_slug' => 'phb:gold-gp',
            'quantity' => 150,
            'equipped' => false,
            'custom_description' => json_encode(['source' => 'starting_wealth']),
        ]);

        $character->load(['characterClasses.characterClass', 'equipment']);

        $choices = $this->handler->getChoices($character);

        expect($choices)->toHaveCount(1);

        $choice = $choices->first();
        expect($choice->remaining)->toBe(0)
            ->and($choice->selected)->toBe(['gold'])
            ->and($choice->metadata)->toHaveKey('gold_amount')
            ->and($choice->metadata['gold_amount'])->toBe(150);
    }

    #[Test]
    public function can_undo_equipment_mode_choice_at_level_1(): void
    {
        $character = Character::factory()->create();
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => 'phb:fighter',
            'level' => 1,
            'is_primary' => true,
        ]);
        $character->load('characterClasses');

        $choice = new PendingChoice(
            id: 'equipment_mode|class|phb:fighter|1|starting_equipment',
            type: 'equipment_mode',
            subtype: null,
            source: 'class',
            sourceName: 'Fighter',
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 0,
            selected: ['equipment'],
            options: [],
            optionsEndpoint: null,
            metadata: [],
        );

        expect($this->handler->canUndo($character, $choice))->toBeTrue();
    }

    #[Test]
    public function cannot_undo_equipment_mode_choice_at_level_2_plus(): void
    {
        $character = Character::factory()->create();
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => 'phb:fighter',
            'level' => 2,
            'is_primary' => true,
        ]);
        $character->load('characterClasses');

        $choice = new PendingChoice(
            id: 'equipment_mode|class|phb:fighter|1|starting_equipment',
            type: 'equipment_mode',
            subtype: null,
            source: 'class',
            sourceName: 'Fighter',
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 0,
            selected: ['equipment'],
            options: [],
            optionsEndpoint: null,
            metadata: [],
        );

        expect($this->handler->canUndo($character, $choice))->toBeFalse();
    }

    #[Test]
    public function undoing_gold_choice_removes_gold_and_resets_mode(): void
    {
        // Create class with known starting wealth average
        $class = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'phb:fighter',
            'starting_wealth_dice' => '5d4',
            'starting_wealth_multiplier' => 10,
        ]);

        $character = Character::factory()->create(['equipment_mode' => 'gold']);
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $class->slug,
            'level' => 1,
            'is_primary' => true,
        ]);

        // Create gold equipment (from starting wealth choice - average is 125)
        CharacterEquipment::create([
            'character_id' => $character->id,
            'item_slug' => 'phb:gold-gp',
            'quantity' => 125,
            'equipped' => false,
        ]);

        $character->load(['characterClasses.characterClass', 'equipment']);

        $choice = new PendingChoice(
            id: 'equipment_mode|class|phb:fighter|1|starting_equipment',
            type: 'equipment_mode',
            subtype: null,
            source: 'class',
            sourceName: 'Fighter',
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 0,
            selected: ['gold'],
            options: [],
            optionsEndpoint: null,
            metadata: [],
        );

        $this->handler->undo($character, $choice);

        $character->refresh();

        // Gold should be removed (was only starting wealth, no background gold)
        expect($character->equipment->where('item_slug', 'phb:gold-gp')->count())->toBe(0);

        // equipment_mode should be reset to null
        expect($character->equipment_mode)->toBeNull();
    }

    #[Test]
    public function undoing_gold_choice_preserves_background_gold(): void
    {
        // Create class with known starting wealth average (125)
        $class = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'phb:fighter',
            'starting_wealth_dice' => '5d4',
            'starting_wealth_multiplier' => 10,
        ]);

        $character = Character::factory()->create(['equipment_mode' => 'gold']);
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $class->slug,
            'level' => 1,
            'is_primary' => true,
        ]);

        // Create background gold entry (10 gp)
        CharacterEquipment::create([
            'character_id' => $character->id,
            'item_slug' => 'phb:gold-gp',
            'quantity' => 10,
            'equipped' => false,
            'custom_description' => json_encode(['source' => 'background']),
        ]);

        // Create starting wealth gold entry (125 gp)
        CharacterEquipment::create([
            'character_id' => $character->id,
            'item_slug' => 'phb:gold-gp',
            'quantity' => 125,
            'equipped' => false,
            'custom_description' => json_encode(['source' => 'starting_wealth']),
        ]);

        $character->load(['characterClasses.characterClass', 'equipment']);

        $choice = new PendingChoice(
            id: 'equipment_mode|class|phb:fighter|1|starting_equipment',
            type: 'equipment_mode',
            subtype: null,
            source: 'class',
            sourceName: 'Fighter',
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 0,
            selected: ['gold'],
            options: [],
            optionsEndpoint: null,
            metadata: [],
        );

        $this->handler->undo($character, $choice);

        $character->refresh();

        // Only background gold (10 gp) should remain
        $totalGold = $character->equipment->where('item_slug', 'phb:gold-gp')->sum('quantity');
        expect($totalGold)->toBe(10);

        // equipment_mode should be reset to null
        expect($character->equipment_mode)->toBeNull();
    }

    #[Test]
    public function resolving_with_gold_tracks_gold_separately_from_background(): void
    {
        Item::factory()->create([
            'name' => 'Gold (gp)',
            'slug' => 'gold-gp',
            'slug' => 'phb:gold-gp',
        ]);

        $class = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'phb:fighter',
            'starting_wealth_dice' => '5d4',
            'starting_wealth_multiplier' => 10,
        ]);

        $character = Character::factory()->create();
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $class->slug,
            'level' => 1,
            'is_primary' => true,
        ]);

        // Existing background gold
        CharacterEquipment::create([
            'character_id' => $character->id,
            'item_slug' => 'phb:gold-gp',
            'quantity' => 15,
            'equipped' => false,
            'custom_description' => json_encode(['source' => 'background']),
        ]);

        $character->load('equipment');

        $choice = new PendingChoice(
            id: 'equipment_mode|class|phb:fighter|1|starting_equipment',
            type: 'equipment_mode',
            subtype: null,
            source: 'class',
            sourceName: 'Fighter',
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: [
                ['value' => 'equipment', 'label' => 'Take Starting Equipment'],
                ['value' => 'gold', 'label' => 'Take Starting Gold'],
            ],
            optionsEndpoint: null,
            metadata: ['starting_wealth' => ['dice' => '5d4', 'multiplier' => 10, 'average' => 125]],
        );

        $this->handler->resolve($character, $choice, ['selected' => ['gold'], 'gold_amount' => 130]);

        $character->refresh();

        // Gold from different sources is tracked separately (15 background + 130 starting wealth)
        $goldEntries = $character->equipment->where('item_slug', 'phb:gold-gp');
        expect($goldEntries)->toHaveCount(2);

        // Total gold should be 145 (15 + 130)
        expect($goldEntries->sum('quantity'))->toBe(145);

        // Currency accessor should sum both entries
        expect($character->currency['gp'])->toBe(145);
    }

    #[Test]
    public function throws_exception_for_invalid_selection(): void
    {
        $choice = new PendingChoice(
            id: 'equipment_mode|class|phb:fighter|1|starting_equipment',
            type: 'equipment_mode',
            subtype: null,
            source: 'class',
            sourceName: 'Fighter',
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: [
                ['value' => 'equipment', 'label' => 'Take Starting Equipment'],
                ['value' => 'gold', 'label' => 'Take Starting Gold'],
            ],
            optionsEndpoint: null,
            metadata: [],
        );

        $character = Character::factory()->create();

        expect(fn () => $this->handler->resolve($character, $choice, ['selected' => ['invalid']]))
            ->toThrow(\App\Exceptions\InvalidSelectionException::class);
    }

    #[Test]
    public function throws_exception_for_empty_selection(): void
    {
        $choice = new PendingChoice(
            id: 'equipment_mode|class|phb:fighter|1|starting_equipment',
            type: 'equipment_mode',
            subtype: null,
            source: 'class',
            sourceName: 'Fighter',
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: [
                ['value' => 'equipment', 'label' => 'Take Starting Equipment'],
                ['value' => 'gold', 'label' => 'Take Starting Gold'],
            ],
            optionsEndpoint: null,
            metadata: [],
        );

        $character = Character::factory()->create();

        expect(fn () => $this->handler->resolve($character, $choice, []))
            ->toThrow(\App\Exceptions\InvalidSelectionException::class);
    }
}
