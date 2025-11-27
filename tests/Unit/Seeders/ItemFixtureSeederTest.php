<?php

namespace Tests\Unit\Seeders;

use App\Models\Item;
use Database\Seeders\Testing\ItemFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ItemFixtureSeederTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true; // Seed lookups

    protected function setUp(): void
    {
        parent::setUp();

        // Create minimal test fixture matching the actual format from ExtractFixturesCommand
        $fixturePath = base_path('tests/fixtures/entities/items.json');
        File::ensureDirectoryExists(dirname($fixturePath));
        File::put($fixturePath, json_encode([
            [
                'name' => 'Test Longsword',
                'slug' => 'test-longsword',
                'item_type' => 'M', // ItemType code for Melee Weapon
                'detail' => null,
                'rarity' => 'common',
                'requires_attunement' => false,
                'is_magic' => false,
                'cost_cp' => 1500,
                'weight' => 3.0,
                'description' => 'A basic longsword.',
                'damage_dice' => '1d8',
                'versatile_damage' => '1d10',
                'damage_type' => 'S', // DamageType code for Slashing
                'range_normal' => null,
                'range_long' => null,
                'armor_class' => null,
                'strength_requirement' => null,
                'stealth_disadvantage' => false,
                'charges_max' => null,
                'recharge_formula' => null,
                'recharge_timing' => null,
                'properties' => ['V'], // ItemProperty code for Versatile
                'source' => 'PHB',
                'pages' => '149',
            ],
            [
                'name' => 'Test Plate Armor',
                'slug' => 'test-plate-armor',
                'item_type' => 'HA', // ItemType code for Heavy Armor
                'detail' => null,
                'rarity' => 'common',
                'requires_attunement' => false,
                'is_magic' => false,
                'cost_cp' => 150000,
                'weight' => 65.0,
                'description' => 'Heavy plate armor.',
                'damage_dice' => null,
                'versatile_damage' => null,
                'damage_type' => null,
                'range_normal' => null,
                'range_long' => null,
                'armor_class' => 18,
                'strength_requirement' => 15,
                'stealth_disadvantage' => true,
                'charges_max' => null,
                'recharge_formula' => null,
                'recharge_timing' => null,
                'properties' => [],
                'source' => 'PHB',
                'pages' => '145',
            ],
        ]));
    }

    protected function tearDown(): void
    {
        // Clean up test fixture
        $fixturePath = base_path('tests/fixtures/entities/items.json');
        if (File::exists($fixturePath)) {
            File::delete($fixturePath);
        }

        parent::tearDown();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_items_from_fixture(): void
    {
        $this->assertDatabaseMissing('items', ['slug' => 'test-longsword']);
        $this->assertDatabaseMissing('items', ['slug' => 'test-plate-armor']);

        $seeder = new ItemFixtureSeeder;
        $seeder->run();

        $this->assertDatabaseHas('items', [
            'slug' => 'test-longsword',
            'name' => 'Test Longsword',
            'rarity' => 'common',
        ]);

        $this->assertDatabaseHas('items', [
            'slug' => 'test-plate-armor',
            'name' => 'Test Plate Armor',
            'armor_class' => 18,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_resolves_item_type_by_code(): void
    {
        $seeder = new ItemFixtureSeeder;
        $seeder->run();

        $longsword = Item::where('slug', 'test-longsword')->first();
        $this->assertNotNull($longsword);
        $this->assertEquals('M', $longsword->itemType->code);

        $plateArmor = Item::where('slug', 'test-plate-armor')->first();
        $this->assertNotNull($plateArmor);
        $this->assertEquals('HA', $plateArmor->itemType->code);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_resolves_damage_type_by_code_for_weapons(): void
    {
        $seeder = new ItemFixtureSeeder;
        $seeder->run();

        $longsword = Item::where('slug', 'test-longsword')->first();
        $this->assertNotNull($longsword);
        $this->assertEquals('S', $longsword->damageType->code);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_attaches_item_properties(): void
    {
        $seeder = new ItemFixtureSeeder;
        $seeder->run();

        $longsword = Item::where('slug', 'test-longsword')->first();
        $this->assertNotNull($longsword);

        // Check that property was attached
        $this->assertCount(1, $longsword->properties);
        $this->assertEquals('V', $longsword->properties->first()->code);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_entity_sources(): void
    {
        $seeder = new ItemFixtureSeeder;
        $seeder->run();

        $longsword = Item::where('slug', 'test-longsword')->first();
        $this->assertNotNull($longsword);

        // Check that entity source was created
        $this->assertCount(1, $longsword->sources);
        $this->assertEquals('PHB', $longsword->sources->first()->source->code);
        $this->assertEquals('149', $longsword->sources->first()->pages);
    }
}
