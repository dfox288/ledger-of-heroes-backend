<?php

namespace Tests\Unit\Services;

use App\Models\Character;
use App\Models\CharacterEquipment;
use App\Models\CharacterFeature;
use App\Models\Feat;
use App\Models\Item;
use App\Models\ItemType;
use App\Models\Modifier;
use App\Services\CharacterStatCalculator;
use Database\Seeders\LookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterStatCalculatorInitiativeTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected $seeder = LookupSeeder::class;

    private CharacterStatCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new CharacterStatCalculator;
    }

    // ==========================================
    // getInitiativeModifiers Tests
    // ==========================================

    #[Test]
    public function it_returns_zero_when_character_has_no_feats_or_items(): void
    {
        $character = Character::factory()->create();

        $bonus = $this->calculator->getInitiativeModifiers($character);

        $this->assertEquals(0, $bonus);
    }

    #[Test]
    public function it_returns_initiative_bonus_from_feat(): void
    {
        $character = Character::factory()->create();

        // Create Alert feat with +5 initiative modifier
        $alertFeat = Feat::factory()->create([
            'slug' => 'phb:alert',
            'name' => 'Alert',
        ]);

        Modifier::create([
            'reference_type' => Feat::class,
            'reference_id' => $alertFeat->id,
            'modifier_category' => 'initiative',
            'value' => 5,
        ]);

        // Grant the feat to the character
        CharacterFeature::create([
            'character_id' => $character->id,
            'feature_type' => Feat::class,
            'feature_id' => $alertFeat->id,
            'feature_slug' => $alertFeat->slug,
            'source' => 'asi_choice',
        ]);

        $bonus = $this->calculator->getInitiativeModifiers($character);

        $this->assertEquals(5, $bonus);
    }

    #[Test]
    public function it_returns_initiative_bonus_from_equipped_item(): void
    {
        $character = Character::factory()->create();

        // Create a magic item with +2 initiative
        $weaponType = ItemType::where('code', 'M')->first();
        $initiativeItem = Item::create([
            'name' => 'Weapon of Warning',
            'slug' => 'weapon-of-warning',
            'item_type_id' => $weaponType->id,
            'rarity' => 'uncommon',
            'description' => 'Grants +2 initiative.',
        ]);

        Modifier::create([
            'reference_type' => Item::class,
            'reference_id' => $initiativeItem->id,
            'modifier_category' => 'initiative',
            'value' => 2,
        ]);

        // Equip the item
        CharacterEquipment::create([
            'character_id' => $character->id,
            'item_slug' => $initiativeItem->slug,
            'equipped' => true,
            'quantity' => 1,
        ]);

        $bonus = $this->calculator->getInitiativeModifiers($character);

        $this->assertEquals(2, $bonus);
    }

    #[Test]
    public function it_does_not_count_unequipped_item_bonuses(): void
    {
        $character = Character::factory()->create();

        // Create a magic item with +2 initiative
        $weaponType = ItemType::where('code', 'M')->first();
        $initiativeItem = Item::create([
            'name' => 'Weapon of Warning',
            'slug' => 'weapon-of-warning',
            'item_type_id' => $weaponType->id,
            'rarity' => 'uncommon',
            'description' => 'Grants +2 initiative.',
        ]);

        Modifier::create([
            'reference_type' => Item::class,
            'reference_id' => $initiativeItem->id,
            'modifier_category' => 'initiative',
            'value' => 2,
        ]);

        // Item in inventory but NOT equipped
        CharacterEquipment::create([
            'character_id' => $character->id,
            'item_slug' => $initiativeItem->slug,
            'equipped' => false,
            'quantity' => 1,
        ]);

        $bonus = $this->calculator->getInitiativeModifiers($character);

        $this->assertEquals(0, $bonus);
    }

    #[Test]
    public function it_stacks_bonuses_from_feat_and_item(): void
    {
        $character = Character::factory()->create();

        // Create Alert feat with +5 initiative
        $alertFeat = Feat::factory()->create([
            'slug' => 'phb:alert',
            'name' => 'Alert',
        ]);

        Modifier::create([
            'reference_type' => Feat::class,
            'reference_id' => $alertFeat->id,
            'modifier_category' => 'initiative',
            'value' => 5,
        ]);

        CharacterFeature::create([
            'character_id' => $character->id,
            'feature_type' => Feat::class,
            'feature_id' => $alertFeat->id,
            'feature_slug' => $alertFeat->slug,
            'source' => 'asi_choice',
        ]);

        // Create magic item with +1 initiative
        $weaponType = ItemType::where('code', 'M')->first();
        $initiativeItem = Item::create([
            'name' => 'Sentinel Shield',
            'slug' => 'sentinel-shield',
            'item_type_id' => $weaponType->id,
            'rarity' => 'uncommon',
            'description' => 'Grants +1 initiative.',
        ]);

        Modifier::create([
            'reference_type' => Item::class,
            'reference_id' => $initiativeItem->id,
            'modifier_category' => 'initiative',
            'value' => 1,
        ]);

        CharacterEquipment::create([
            'character_id' => $character->id,
            'item_slug' => $initiativeItem->slug,
            'equipped' => true,
            'quantity' => 1,
        ]);

        $bonus = $this->calculator->getInitiativeModifiers($character);

        $this->assertEquals(6, $bonus); // 5 + 1
    }

    #[Test]
    public function it_ignores_feats_without_initiative_modifiers(): void
    {
        $character = Character::factory()->create();

        // Create a feat without initiative modifier (e.g., Tough)
        $toughFeat = Feat::factory()->create([
            'slug' => 'phb:tough',
            'name' => 'Tough',
        ]);

        Modifier::create([
            'reference_type' => Feat::class,
            'reference_id' => $toughFeat->id,
            'modifier_category' => 'hit_points_per_level',
            'value' => 2,
        ]);

        CharacterFeature::create([
            'character_id' => $character->id,
            'feature_type' => Feat::class,
            'feature_id' => $toughFeat->id,
            'feature_slug' => $toughFeat->slug,
            'source' => 'asi_choice',
        ]);

        $bonus = $this->calculator->getInitiativeModifiers($character);

        $this->assertEquals(0, $bonus);
    }
}
