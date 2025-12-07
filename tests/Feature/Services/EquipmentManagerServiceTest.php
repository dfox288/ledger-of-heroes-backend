<?php

namespace Tests\Feature\Services;

use App\Models\Background;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use App\Models\CharacterEquipment;
use App\Models\EntityItem;
use App\Models\Item;
use App\Services\EquipmentManagerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('feature-db')]
class EquipmentManagerServiceTest extends TestCase
{
    use RefreshDatabase;

    private EquipmentManagerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EquipmentManagerService::class);
    }

    #[Test]
    public function populate_from_class_grants_fixed_equipment_from_primary_class(): void
    {
        // Create items
        $leatherArmor = Item::factory()->create([
            'name' => 'Leather Armor',
            'slug' => 'leather-armor',
            'full_slug' => 'phb:leather-armor',
        ]);
        $dagger = Item::factory()->create([
            'name' => 'Dagger',
            'slug' => 'dagger',
            'full_slug' => 'phb:dagger',
        ]);

        // Create class with fixed equipment
        $bard = CharacterClass::factory()->create([
            'name' => 'Bard',
            'slug' => 'bard',
            'full_slug' => 'phb:bard',
        ]);

        // Add fixed equipment (is_choice = false)
        EntityItem::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $bard->id,
            'item_id' => $leatherArmor->id,
            'quantity' => 1,
            'is_choice' => false,
        ]);
        EntityItem::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $bard->id,
            'item_id' => $dagger->id,
            'quantity' => 1,
            'is_choice' => false,
        ]);

        // Create character with class
        $character = Character::factory()->create();
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $bard->full_slug,
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
        ]);

        // Act
        $this->service->populateFromClass($character->fresh());

        // Assert
        $equipment = $character->fresh()->equipment;
        $this->assertCount(2, $equipment);

        $itemSlugs = $equipment->pluck('item_slug')->sort()->values()->toArray();
        $this->assertEquals(['phb:dagger', 'phb:leather-armor'], $itemSlugs);
    }

    #[Test]
    public function populate_from_class_does_not_grant_choice_equipment(): void
    {
        $chainMail = Item::factory()->create([
            'name' => 'Chain Mail',
            'slug' => 'chain-mail',
            'full_slug' => 'phb:chain-mail',
        ]);

        $fighter = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter',
            'full_slug' => 'phb:fighter',
        ]);

        // Add choice equipment (is_choice = true)
        EntityItem::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $fighter->id,
            'item_id' => $chainMail->id,
            'quantity' => 1,
            'is_choice' => true,
            'choice_group' => 'equipment_choice_1',
            'choice_option' => 1,
        ]);

        $character = Character::factory()->create();
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $fighter->full_slug,
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
        ]);

        $this->service->populateFromClass($character->fresh());

        $this->assertCount(0, $character->fresh()->equipment);
    }

    #[Test]
    public function populate_from_class_does_not_duplicate_existing_equipment(): void
    {
        $dagger = Item::factory()->create([
            'name' => 'Dagger',
            'slug' => 'dagger',
            'full_slug' => 'phb:dagger',
        ]);

        $rogue = CharacterClass::factory()->create([
            'name' => 'Rogue',
            'slug' => 'rogue',
            'full_slug' => 'phb:rogue',
        ]);

        EntityItem::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $rogue->id,
            'item_id' => $dagger->id,
            'quantity' => 1,
            'is_choice' => false,
        ]);

        $character = Character::factory()->create();
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $rogue->full_slug,
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
        ]);

        // Add equipment manually first
        CharacterEquipment::create([
            'character_id' => $character->id,
            'item_slug' => 'phb:dagger',
            'quantity' => 1,
            'equipped' => false,
            'custom_description' => json_encode(['source' => 'class']),
        ]);

        // Act - should not duplicate
        $this->service->populateFromClass($character->fresh());

        $this->assertCount(1, $character->fresh()->equipment);
    }

    #[Test]
    public function populate_from_class_does_not_grant_equipment_for_multiclass(): void
    {
        $dagger = Item::factory()->create([
            'name' => 'Dagger',
            'slug' => 'dagger',
            'full_slug' => 'phb:dagger',
        ]);

        $primaryClass = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter',
            'full_slug' => 'phb:fighter',
        ]);

        $secondaryClass = CharacterClass::factory()->create([
            'name' => 'Rogue',
            'slug' => 'rogue',
            'full_slug' => 'phb:rogue',
        ]);

        // Only secondary class has fixed equipment
        EntityItem::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $secondaryClass->id,
            'item_id' => $dagger->id,
            'quantity' => 1,
            'is_choice' => false,
        ]);

        $character = Character::factory()->create();

        // Primary class (no equipment)
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $primaryClass->full_slug,
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
        ]);

        // Secondary class (has equipment but shouldn't be granted)
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $secondaryClass->full_slug,
            'level' => 1,
            'is_primary' => false,
            'order' => 2,
        ]);

        $this->service->populateFromClass($character->fresh());

        // Only primary class equipment should be granted (none in this case)
        $this->assertCount(0, $character->fresh()->equipment);
    }

    #[Test]
    public function populate_from_class_handles_character_with_no_class(): void
    {
        $character = Character::factory()->create();

        // Should not throw
        $this->service->populateFromClass($character);

        $this->assertCount(0, $character->fresh()->equipment);
    }

    #[Test]
    public function populate_from_class_grants_equipment_with_correct_quantity(): void
    {
        $arrow = Item::factory()->create([
            'name' => 'Arrow',
            'slug' => 'arrow',
            'full_slug' => 'phb:arrow',
        ]);

        $ranger = CharacterClass::factory()->create([
            'name' => 'Ranger',
            'slug' => 'ranger',
            'full_slug' => 'phb:ranger',
        ]);

        EntityItem::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $ranger->id,
            'item_id' => $arrow->id,
            'quantity' => 20,
            'is_choice' => false,
        ]);

        $character = Character::factory()->create();
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $ranger->full_slug,
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
        ]);

        $this->service->populateFromClass($character->fresh());

        $equipment = $character->fresh()->equipment->first();
        $this->assertEquals(20, $equipment->quantity);
    }

    #[Test]
    public function populate_from_background_grants_fixed_equipment(): void
    {
        $holySymbol = Item::factory()->create([
            'name' => 'Holy Symbol',
            'slug' => 'holy-symbol',
            'full_slug' => 'phb:holy-symbol',
        ]);
        $prayerBook = Item::factory()->create([
            'name' => 'Prayer Book',
            'slug' => 'prayer-book',
            'full_slug' => 'phb:prayer-book',
        ]);

        $acolyte = Background::factory()->create([
            'name' => 'Acolyte',
            'slug' => 'acolyte',
            'full_slug' => 'phb:acolyte',
        ]);

        EntityItem::create([
            'reference_type' => Background::class,
            'reference_id' => $acolyte->id,
            'item_id' => $holySymbol->id,
            'quantity' => 1,
            'is_choice' => false,
        ]);
        EntityItem::create([
            'reference_type' => Background::class,
            'reference_id' => $acolyte->id,
            'item_id' => $prayerBook->id,
            'quantity' => 1,
            'is_choice' => false,
        ]);

        $character = Character::factory()->create([
            'background_slug' => $acolyte->full_slug,
        ]);

        $this->service->populateFromBackground($character);

        $equipment = $character->fresh()->equipment;
        $this->assertCount(2, $equipment);

        $itemSlugs = $equipment->pluck('item_slug')->sort()->values()->toArray();
        $this->assertEquals(['phb:holy-symbol', 'phb:prayer-book'], $itemSlugs);
    }

    #[Test]
    public function populate_from_background_handles_character_with_no_background(): void
    {
        $character = Character::factory()->create([
            'background_slug' => null,
        ]);

        $this->service->populateFromBackground($character);

        $this->assertCount(0, $character->fresh()->equipment);
    }

    #[Test]
    public function populate_from_background_does_not_duplicate_existing_equipment(): void
    {
        $holySymbol = Item::factory()->create([
            'name' => 'Holy Symbol',
            'slug' => 'holy-symbol',
            'full_slug' => 'phb:holy-symbol',
        ]);

        $acolyte = Background::factory()->create([
            'name' => 'Acolyte',
            'slug' => 'acolyte',
            'full_slug' => 'phb:acolyte',
        ]);

        EntityItem::create([
            'reference_type' => Background::class,
            'reference_id' => $acolyte->id,
            'item_id' => $holySymbol->id,
            'quantity' => 1,
            'is_choice' => false,
        ]);

        $character = Character::factory()->create([
            'background_slug' => $acolyte->full_slug,
        ]);

        // Add equipment manually first
        CharacterEquipment::create([
            'character_id' => $character->id,
            'item_slug' => 'phb:holy-symbol',
            'quantity' => 1,
            'equipped' => false,
            'custom_description' => json_encode(['source' => 'background']),
        ]);

        $this->service->populateFromBackground($character);

        $this->assertCount(1, $character->fresh()->equipment);
    }

    #[Test]
    public function populate_from_background_stores_correct_source_metadata(): void
    {
        $holySymbol = Item::factory()->create([
            'name' => 'Holy Symbol',
            'slug' => 'holy-symbol',
            'full_slug' => 'phb:holy-symbol',
        ]);

        $acolyte = Background::factory()->create([
            'name' => 'Acolyte',
            'slug' => 'acolyte',
            'full_slug' => 'phb:acolyte',
        ]);

        EntityItem::create([
            'reference_type' => Background::class,
            'reference_id' => $acolyte->id,
            'item_id' => $holySymbol->id,
            'quantity' => 1,
            'is_choice' => false,
        ]);

        $character = Character::factory()->create([
            'background_slug' => $acolyte->full_slug,
        ]);

        $this->service->populateFromBackground($character);

        $equipment = $character->fresh()->equipment->first();
        $metadata = json_decode($equipment->custom_description, true);

        $this->assertEquals(['source' => 'background'], $metadata);
    }

    #[Test]
    public function populate_all_grants_equipment_from_both_class_and_background(): void
    {
        $dagger = Item::factory()->create([
            'name' => 'Dagger',
            'slug' => 'dagger',
            'full_slug' => 'phb:dagger',
        ]);
        $holySymbol = Item::factory()->create([
            'name' => 'Holy Symbol',
            'slug' => 'holy-symbol',
            'full_slug' => 'phb:holy-symbol',
        ]);

        $rogue = CharacterClass::factory()->create([
            'name' => 'Rogue',
            'slug' => 'rogue',
            'full_slug' => 'phb:rogue',
        ]);

        $acolyte = Background::factory()->create([
            'name' => 'Acolyte',
            'slug' => 'acolyte',
            'full_slug' => 'phb:acolyte',
        ]);

        EntityItem::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $rogue->id,
            'item_id' => $dagger->id,
            'quantity' => 1,
            'is_choice' => false,
        ]);

        EntityItem::create([
            'reference_type' => Background::class,
            'reference_id' => $acolyte->id,
            'item_id' => $holySymbol->id,
            'quantity' => 1,
            'is_choice' => false,
        ]);

        $character = Character::factory()->create([
            'background_slug' => $acolyte->full_slug,
        ]);
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $rogue->full_slug,
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
        ]);

        $this->service->populateAll($character->fresh());

        $equipment = $character->fresh()->equipment;
        $this->assertCount(2, $equipment);

        $itemSlugs = $equipment->pluck('item_slug')->sort()->values()->toArray();
        $this->assertEquals(['phb:dagger', 'phb:holy-symbol'], $itemSlugs);
    }
}
