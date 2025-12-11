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
            'slug' => 'phb:leather-armor',
        ]);
        $dagger = Item::factory()->create([
            'name' => 'Dagger',
            'slug' => 'dagger',
            'slug' => 'phb:dagger',
        ]);

        // Create class with fixed equipment
        $bard = CharacterClass::factory()->create([
            'name' => 'Bard',
            'slug' => 'bard',
            'slug' => 'phb:bard',
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
            'class_slug' => $bard->slug,
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
            'slug' => 'phb:chain-mail',
        ]);

        $fighter = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter',
            'slug' => 'phb:fighter',
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
            'class_slug' => $fighter->slug,
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
            'slug' => 'phb:dagger',
        ]);

        $rogue = CharacterClass::factory()->create([
            'name' => 'Rogue',
            'slug' => 'rogue',
            'slug' => 'phb:rogue',
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
            'class_slug' => $rogue->slug,
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
            'slug' => 'phb:dagger',
        ]);

        $primaryClass = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter',
            'slug' => 'phb:fighter',
        ]);

        $secondaryClass = CharacterClass::factory()->create([
            'name' => 'Rogue',
            'slug' => 'rogue',
            'slug' => 'phb:rogue',
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
            'class_slug' => $primaryClass->slug,
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
        ]);

        // Secondary class (has equipment but shouldn't be granted)
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $secondaryClass->slug,
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
            'slug' => 'phb:arrow',
        ]);

        $ranger = CharacterClass::factory()->create([
            'name' => 'Ranger',
            'slug' => 'ranger',
            'slug' => 'phb:ranger',
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
            'class_slug' => $ranger->slug,
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
            'slug' => 'phb:holy-symbol',
        ]);
        $prayerBook = Item::factory()->create([
            'name' => 'Prayer Book',
            'slug' => 'prayer-book',
            'slug' => 'phb:prayer-book',
        ]);

        $acolyte = Background::factory()->create([
            'name' => 'Acolyte',
            'slug' => 'acolyte',
            'slug' => 'phb:acolyte',
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
            'background_slug' => $acolyte->slug,
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
            'slug' => 'phb:holy-symbol',
        ]);

        $acolyte = Background::factory()->create([
            'name' => 'Acolyte',
            'slug' => 'acolyte',
            'slug' => 'phb:acolyte',
        ]);

        EntityItem::create([
            'reference_type' => Background::class,
            'reference_id' => $acolyte->id,
            'item_id' => $holySymbol->id,
            'quantity' => 1,
            'is_choice' => false,
        ]);

        $character = Character::factory()->create([
            'background_slug' => $acolyte->slug,
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
            'slug' => 'phb:holy-symbol',
        ]);

        $acolyte = Background::factory()->create([
            'name' => 'Acolyte',
            'slug' => 'acolyte',
            'slug' => 'phb:acolyte',
        ]);

        EntityItem::create([
            'reference_type' => Background::class,
            'reference_id' => $acolyte->id,
            'item_id' => $holySymbol->id,
            'quantity' => 1,
            'is_choice' => false,
        ]);

        $character = Character::factory()->create([
            'background_slug' => $acolyte->slug,
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
            'slug' => 'phb:dagger',
        ]);
        $holySymbol = Item::factory()->create([
            'name' => 'Holy Symbol',
            'slug' => 'holy-symbol',
            'slug' => 'phb:holy-symbol',
        ]);

        $rogue = CharacterClass::factory()->create([
            'name' => 'Rogue',
            'slug' => 'rogue',
            'slug' => 'phb:rogue',
        ]);

        $acolyte = Background::factory()->create([
            'name' => 'Acolyte',
            'slug' => 'acolyte',
            'slug' => 'phb:acolyte',
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
            'background_slug' => $acolyte->slug,
        ]);
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $rogue->slug,
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

    #[Test]
    public function changing_background_keeps_equipment_from_both_backgrounds(): void
    {
        $holySymbol = Item::factory()->create([
            'name' => 'Holy Symbol',
            'slug' => 'holy-symbol',
            'slug' => 'phb:holy-symbol',
        ]);
        $thievesTools = Item::factory()->create([
            'name' => "Thieves' Tools",
            'slug' => 'thieves-tools',
            'slug' => 'phb:thieves-tools',
        ]);

        $acolyte = Background::factory()->create([
            'name' => 'Acolyte',
            'slug' => 'acolyte',
            'slug' => 'phb:acolyte',
        ]);
        $criminal = Background::factory()->create([
            'name' => 'Criminal',
            'slug' => 'criminal',
            'slug' => 'phb:criminal',
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
            'reference_id' => $criminal->id,
            'item_id' => $thievesTools->id,
            'quantity' => 1,
            'is_choice' => false,
        ]);

        // Create character with first background
        $character = Character::factory()->create([
            'background_slug' => $acolyte->slug,
        ]);

        // Grant equipment from first background
        $this->service->populateFromBackground($character);
        $this->assertCount(1, $character->fresh()->equipment);

        // Change to second background
        $character->update(['background_slug' => $criminal->slug]);

        // Grant equipment from second background
        $this->service->populateFromBackground($character->fresh());

        // Should have equipment from BOTH backgrounds (no removal)
        $equipment = $character->fresh()->equipment;
        $this->assertCount(2, $equipment);

        $itemSlugs = $equipment->pluck('item_slug')->sort()->values()->toArray();
        $this->assertEquals(['phb:holy-symbol', 'phb:thieves-tools'], $itemSlugs);
    }

    #[Test]
    public function populate_skips_equipment_with_neither_item_nor_description(): void
    {
        $validItem = Item::factory()->create([
            'name' => 'Dagger',
            'slug' => 'dagger',
            'slug' => 'phb:dagger',
        ]);

        $rogue = CharacterClass::factory()->create([
            'name' => 'Rogue',
            'slug' => 'rogue',
            'slug' => 'phb:rogue',
        ]);

        // Create EntityItem with valid item
        EntityItem::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $rogue->id,
            'item_id' => $validItem->id,
            'quantity' => 1,
            'is_choice' => false,
        ]);

        // Create EntityItem with null item_id AND null description (orphaned record)
        EntityItem::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $rogue->id,
            'item_id' => null,
            'description' => null,
            'quantity' => 1,
            'is_choice' => false,
        ]);

        $character = Character::factory()->create();
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $rogue->slug,
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
        ]);

        // Should not throw, should skip empty record and grant valid one
        $this->service->populateFromClass($character->fresh());

        $equipment = $character->fresh()->equipment;
        $this->assertCount(1, $equipment);
        $this->assertEquals('phb:dagger', $equipment->first()->item_slug);
    }

    #[Test]
    public function populate_from_background_grants_description_only_equipment(): void
    {
        // Acolyte background has description-only items like "holy symbol (a gift...)"
        $acolyte = Background::factory()->create([
            'name' => 'Acolyte',
            'slug' => 'acolyte',
            'slug' => 'phb:acolyte',
        ]);

        // Description-only item (no item_id)
        EntityItem::create([
            'reference_type' => Background::class,
            'reference_id' => $acolyte->id,
            'item_id' => null,
            'description' => 'A holy symbol (a gift to you when you entered the priesthood)',
            'quantity' => 1,
            'is_choice' => false,
        ]);

        EntityItem::create([
            'reference_type' => Background::class,
            'reference_id' => $acolyte->id,
            'item_id' => null,
            'description' => '5 sticks of incense',
            'quantity' => 5,
            'is_choice' => false,
        ]);

        $character = Character::factory()->create([
            'background_slug' => $acolyte->slug,
        ]);

        $this->service->populateFromBackground($character);

        $equipment = $character->fresh()->equipment;
        $this->assertCount(2, $equipment);

        // Verify description-only items have null item_slug
        $descriptionItems = $equipment->whereNull('item_slug');
        $this->assertCount(2, $descriptionItems);

        // Verify metadata contains the description
        $holySymbol = $equipment->first(function ($item) {
            $meta = json_decode($item->custom_description, true);

            return str_contains($meta['description'] ?? '', 'holy symbol');
        });
        $this->assertNotNull($holySymbol);
        $this->assertEquals(1, $holySymbol->quantity);

        $incense = $equipment->first(function ($item) {
            $meta = json_decode($item->custom_description, true);

            return str_contains($meta['description'] ?? '', 'incense');
        });
        $this->assertNotNull($incense);
        $this->assertEquals(5, $incense->quantity);
    }

    #[Test]
    public function description_only_equipment_sets_custom_name(): void
    {
        $acolyte = Background::factory()->create([
            'name' => 'Acolyte',
            'slug' => 'acolyte',
            'slug' => 'phb:acolyte',
        ]);

        // Description-only item (no item_id)
        EntityItem::create([
            'reference_type' => Background::class,
            'reference_id' => $acolyte->id,
            'item_id' => null,
            'description' => 'A holy symbol (a gift to you when you entered the priesthood)',
            'quantity' => 1,
            'is_choice' => false,
        ]);

        $character = Character::factory()->create([
            'background_slug' => $acolyte->slug,
        ]);

        $this->service->populateFromBackground($character);

        $equipment = $character->fresh()->equipment->first();

        // custom_name should be set to the description for display purposes
        $this->assertEquals(
            'A holy symbol (a gift to you when you entered the priesthood)',
            $equipment->custom_name
        );
    }

    #[Test]
    public function populate_grants_both_item_and_description_equipment(): void
    {
        $pouch = Item::factory()->create([
            'name' => 'Pouch',
            'slug' => 'pouch',
            'slug' => 'phb:pouch',
        ]);

        $acolyte = Background::factory()->create([
            'name' => 'Acolyte',
            'slug' => 'acolyte',
            'slug' => 'phb:acolyte',
        ]);

        // Item with item_id
        EntityItem::create([
            'reference_type' => Background::class,
            'reference_id' => $acolyte->id,
            'item_id' => $pouch->id,
            'quantity' => 1,
            'is_choice' => false,
        ]);

        // Description-only item
        EntityItem::create([
            'reference_type' => Background::class,
            'reference_id' => $acolyte->id,
            'item_id' => null,
            'description' => 'A prayer book or prayer wheel',
            'quantity' => 1,
            'is_choice' => false,
        ]);

        $character = Character::factory()->create([
            'background_slug' => $acolyte->slug,
        ]);

        $this->service->populateFromBackground($character);

        $equipment = $character->fresh()->equipment;
        $this->assertCount(2, $equipment);

        // One with item_slug, one without
        $this->assertEquals(1, $equipment->whereNotNull('item_slug')->count());
        $this->assertEquals(1, $equipment->whereNull('item_slug')->count());
    }

    #[Test]
    public function populate_description_only_does_not_duplicate(): void
    {
        $acolyte = Background::factory()->create([
            'name' => 'Acolyte',
            'slug' => 'acolyte',
            'slug' => 'phb:acolyte',
        ]);

        EntityItem::create([
            'reference_type' => Background::class,
            'reference_id' => $acolyte->id,
            'item_id' => null,
            'description' => 'A holy symbol (a gift)',
            'quantity' => 1,
            'is_choice' => false,
        ]);

        $character = Character::factory()->create([
            'background_slug' => $acolyte->slug,
        ]);

        // Manually add the description-only item first
        CharacterEquipment::create([
            'character_id' => $character->id,
            'item_slug' => null,
            'quantity' => 1,
            'equipped' => false,
            'custom_description' => json_encode([
                'source' => 'background',
                'description' => 'A holy symbol (a gift)',
            ]),
        ]);

        // Populate again - should not duplicate
        $this->service->populateFromBackground($character->fresh());

        $this->assertCount(1, $character->fresh()->equipment);
    }

    #[Test]
    public function description_only_equipment_stores_correct_metadata(): void
    {
        $acolyte = Background::factory()->create([
            'name' => 'Acolyte',
            'slug' => 'acolyte',
            'slug' => 'phb:acolyte',
        ]);

        EntityItem::create([
            'reference_type' => Background::class,
            'reference_id' => $acolyte->id,
            'item_id' => null,
            'description' => 'Vestments',
            'quantity' => 1,
            'is_choice' => false,
        ]);

        $character = Character::factory()->create([
            'background_slug' => $acolyte->slug,
        ]);

        $this->service->populateFromBackground($character);

        $equipment = $character->fresh()->equipment->first();
        $metadata = json_decode($equipment->custom_description, true);

        $this->assertArrayHasKey('source', $metadata);
        $this->assertArrayHasKey('description', $metadata);
        $this->assertEquals('background', $metadata['source']);
        $this->assertEquals('Vestments', $metadata['description']);
    }
}
