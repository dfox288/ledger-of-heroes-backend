<?php

namespace Tests\Feature\Api;

use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use App\Models\CharacterEquipment;
use App\Models\ClassFeature;
use App\Models\Item;
use App\Models\ItemType;
use App\Models\Modifier;
use Database\Seeders\LookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for dynamic attunement slots based on class features.
 *
 * D&D 5e rules:
 * - Default max attunement is 3
 * - Artificer class features grant additional slots:
 *   - Level 10 Magic Item Adept: 4 slots
 *   - Level 14 Magic Item Savant: 5 slots
 *   - Level 18 Magic Item Master: 6 slots
 *
 * Covers issue #592.
 */
class CharacterDynamicAttunementTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected $seeder = LookupSeeder::class;

    private CharacterClass $artificerClass;

    private ClassFeature $magicItemAdept;

    private ClassFeature $magicItemSavant;

    private ClassFeature $magicItemMaster;

    private Item $magicItem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestFixtures();
    }

    private function createTestFixtures(): void
    {
        // Create Artificer class for testing
        $this->artificerClass = CharacterClass::factory()->create([
            'name' => 'Test Artificer',
            'slug' => 'test:artificer',
        ]);

        // Create class features with attunement_max modifiers
        $this->magicItemAdept = ClassFeature::factory()
            ->forClass($this->artificerClass)
            ->atLevel(10)
            ->create(['feature_name' => 'Magic Item Adept']);

        $this->magicItemSavant = ClassFeature::factory()
            ->forClass($this->artificerClass)
            ->atLevel(14)
            ->create(['feature_name' => 'Magic Item Savant']);

        $this->magicItemMaster = ClassFeature::factory()
            ->forClass($this->artificerClass)
            ->atLevel(18)
            ->create(['feature_name' => 'Magic Item Master']);

        // Attach modifiers to features
        Modifier::create([
            'reference_type' => ClassFeature::class,
            'reference_id' => $this->magicItemAdept->id,
            'modifier_category' => 'attunement_max',
            'value' => 4,
        ]);

        Modifier::create([
            'reference_type' => ClassFeature::class,
            'reference_id' => $this->magicItemSavant->id,
            'modifier_category' => 'attunement_max',
            'value' => 5,
        ]);

        Modifier::create([
            'reference_type' => ClassFeature::class,
            'reference_id' => $this->magicItemMaster->id,
            'modifier_category' => 'attunement_max',
            'value' => 6,
        ]);

        // Create magic item for attunement tests
        $wonderousType = ItemType::where('code', 'W')->first();
        $this->magicItem = Item::create([
            'name' => 'Test Magic Ring',
            'slug' => 'test:magic-ring-attunement',
            'item_type_id' => $wonderousType->id,
            'rarity' => 'rare',
            'requires_attunement' => true,
            'description' => 'A test magic item.',
        ]);
    }

    // =============================
    // ClassFeature HasModifiers Trait
    // =============================

    #[Test]
    public function class_feature_can_have_modifiers(): void
    {
        $feature = ClassFeature::factory()->create();

        Modifier::create([
            'reference_type' => ClassFeature::class,
            'reference_id' => $feature->id,
            'modifier_category' => 'attunement_max',
            'value' => 4,
        ]);

        $feature->refresh();

        expect($feature->modifiers)->toHaveCount(1);
        expect($feature->modifiers->first()->modifier_category)->toBe('attunement_max');
        expect((int) $feature->modifiers->first()->value)->toBe(4);
    }

    // =============================
    // Character max_attunement_slots Accessor
    // =============================

    #[Test]
    public function character_without_attunement_features_has_default_max_of_three(): void
    {
        $character = Character::factory()->create();

        // Assign a non-Artificer class
        $fighterClass = CharacterClass::factory()->create(['name' => 'Fighter', 'slug' => 'test:fighter']);
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $fighterClass->slug,
            'level' => 10,
            'is_primary' => true,
            'order' => 1,
        ]);

        expect($character->max_attunement_slots)->toBe(3);
    }

    #[Test]
    public function artificer_level_9_has_default_max_of_three(): void
    {
        $character = Character::factory()->create();

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $this->artificerClass->slug,
            'level' => 9,
            'is_primary' => true,
            'order' => 1,
        ]);

        expect($character->max_attunement_slots)->toBe(3);
    }

    #[Test]
    public function artificer_level_10_has_max_of_four(): void
    {
        $character = Character::factory()->create();

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $this->artificerClass->slug,
            'level' => 10,
            'is_primary' => true,
            'order' => 1,
        ]);

        expect($character->max_attunement_slots)->toBe(4);
    }

    #[Test]
    public function artificer_level_14_has_max_of_five(): void
    {
        $character = Character::factory()->create();

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $this->artificerClass->slug,
            'level' => 14,
            'is_primary' => true,
            'order' => 1,
        ]);

        expect($character->max_attunement_slots)->toBe(5);
    }

    #[Test]
    public function artificer_level_18_has_max_of_six(): void
    {
        $character = Character::factory()->create();

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $this->artificerClass->slug,
            'level' => 18,
            'is_primary' => true,
            'order' => 1,
        ]);

        expect($character->max_attunement_slots)->toBe(6);
    }

    #[Test]
    public function multiclass_artificer_uses_artificer_level_not_total(): void
    {
        $character = Character::factory()->create();

        // Fighter 10 / Artificer 10 = total level 20, but Artificer level 10
        $fighterClass = CharacterClass::factory()->create(['name' => 'Fighter', 'slug' => 'test:fighter-mc']);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $fighterClass->slug,
            'level' => 10,
            'is_primary' => true,
            'order' => 1,
        ]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $this->artificerClass->slug,
            'level' => 10,
            'is_primary' => false,
            'order' => 2,
        ]);

        // Should be 4 (from Artificer 10), not 5 or 6
        expect($character->max_attunement_slots)->toBe(4);
    }

    // =============================
    // API Response with Dynamic Max
    // =============================

    #[Test]
    public function api_response_shows_dynamic_attunement_max(): void
    {
        $character = Character::factory()->create();

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $this->artificerClass->slug,
            'level' => 14,
            'is_primary' => true,
            'order' => 1,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}");

        $response->assertOk()
            ->assertJsonPath('data.attunement_slots.max', 5);
    }

    #[Test]
    public function non_artificer_api_response_shows_default_max(): void
    {
        $character = Character::factory()->create();

        $fighterClass = CharacterClass::factory()->create(['name' => 'Fighter', 'slug' => 'test:fighter-api']);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $fighterClass->slug,
            'level' => 20,
            'is_primary' => true,
            'order' => 1,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}");

        $response->assertOk()
            ->assertJsonPath('data.attunement_slots.max', 3);
    }

    // =============================
    // Attunement Limit Enforcement
    // =============================

    #[Test]
    public function artificer_can_attune_to_fourth_item_at_level_10(): void
    {
        $character = Character::factory()->create();

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $this->artificerClass->slug,
            'level' => 10,
            'is_primary' => true,
            'order' => 1,
        ]);

        // Create and attune to 3 items
        for ($i = 1; $i <= 3; $i++) {
            $item = Item::create([
                'name' => "Magic Item {$i}",
                'slug' => "test:magic-item-{$i}",
                'item_type_id' => ItemType::where('code', 'W')->first()->id,
                'rarity' => 'rare',
                'requires_attunement' => true,
                'description' => 'Test item.',
            ]);

            CharacterEquipment::factory()
                ->withItem($item)
                ->create([
                    'character_id' => $character->id,
                    'is_attuned' => true,
                ]);
        }

        // Fourth item should succeed for Artificer 10
        $fourthItem = Item::create([
            'name' => 'Fourth Magic Item',
            'slug' => 'test:fourth-magic-item',
            'item_type_id' => ItemType::where('code', 'W')->first()->id,
            'rarity' => 'rare',
            'requires_attunement' => true,
            'description' => 'Test item.',
        ]);

        $fourthEquipment = CharacterEquipment::factory()
            ->withItem($fourthItem)
            ->create([
                'character_id' => $character->id,
                'is_attuned' => false,
            ]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$fourthEquipment->id}",
            ['is_attuned' => true]
        );

        $response->assertOk()
            ->assertJsonPath('data.is_attuned', true);
    }

    #[Test]
    public function artificer_cannot_attune_beyond_their_max(): void
    {
        $character = Character::factory()->create();

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $this->artificerClass->slug,
            'level' => 10, // Max 4
            'is_primary' => true,
            'order' => 1,
        ]);

        // Create and attune to 4 items
        for ($i = 1; $i <= 4; $i++) {
            $item = Item::create([
                'name' => "Magic Item {$i}",
                'slug' => "test:magic-item-limit-{$i}",
                'item_type_id' => ItemType::where('code', 'W')->first()->id,
                'rarity' => 'rare',
                'requires_attunement' => true,
                'description' => 'Test item.',
            ]);

            CharacterEquipment::factory()
                ->withItem($item)
                ->create([
                    'character_id' => $character->id,
                    'is_attuned' => true,
                ]);
        }

        // Fifth item should fail
        $fifthItem = Item::create([
            'name' => 'Fifth Magic Item',
            'slug' => 'test:fifth-magic-item',
            'item_type_id' => ItemType::where('code', 'W')->first()->id,
            'rarity' => 'rare',
            'requires_attunement' => true,
            'description' => 'Test item.',
        ]);

        $fifthEquipment = CharacterEquipment::factory()
            ->withItem($fifthItem)
            ->create([
                'character_id' => $character->id,
                'is_attuned' => false,
            ]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$fifthEquipment->id}",
            ['is_attuned' => true]
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['is_attuned']);
    }
}
