<?php

namespace Tests\Feature\Api;

use App\Models\Item;
use App\Models\Spell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemSpellFilteringApiTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_items_by_single_spell(): void
    {
        // Create spells
        $fireball = Spell::factory()->create([
            'name' => 'Fireball',
            'slug' => 'fireball',
            'level' => 3,
        ]);

        $lightningBolt = Spell::factory()->create([
            'name' => 'Lightning Bolt',
            'slug' => 'lightning-bolt',
            'level' => 3,
        ]);

        // Create items
        $wandOfFireballs = Item::factory()->create([
            'name' => 'Wand of Fireballs',
            'slug' => 'wand-of-fireballs',
        ]);
        $wandOfFireballs->spells()->attach($fireball->id);

        $staffOfPower = Item::factory()->create([
            'name' => 'Staff of Power',
            'slug' => 'staff-of-power',
        ]);
        $staffOfPower->spells()->attach([$fireball->id, $lightningBolt->id]);

        $potionOfHealing = Item::factory()->create([
            'name' => 'Potion of Healing',
            'slug' => 'potion-of-healing',
        ]);
        // No spells attached

        // Act
        $response = $this->getJson('/api/v1/items?spells=fireball');

        // Assert
        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(2, $data, 'Should return 2 items with Fireball');

        $itemSlugs = collect($data)->pluck('slug')->sort()->values()->all();
        $this->assertEquals(['staff-of-power', 'wand-of-fireballs'], $itemSlugs);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_items_by_multiple_spells_with_and_operator(): void
    {
        // Create spells
        $fireball = Spell::factory()->create([
            'name' => 'Fireball',
            'slug' => 'fireball',
            'level' => 3,
        ]);

        $lightningBolt = Spell::factory()->create([
            'name' => 'Lightning Bolt',
            'slug' => 'lightning-bolt',
            'level' => 3,
        ]);

        $teleport = Spell::factory()->create([
            'name' => 'Teleport',
            'slug' => 'teleport',
            'level' => 7,
        ]);

        // Create items
        $wandOfFireballs = Item::factory()->create(['name' => 'Wand of Fireballs']);
        $wandOfFireballs->spells()->attach($fireball->id);

        $staffOfPower = Item::factory()->create(['name' => 'Staff of Power']);
        $staffOfPower->spells()->attach([$fireball->id, $lightningBolt->id, $teleport->id]);

        $wandOfLightning = Item::factory()->create(['name' => 'Wand of Lightning Bolts']);
        $wandOfLightning->spells()->attach($lightningBolt->id);

        // Act - AND operator (default)
        $response = $this->getJson('/api/v1/items?spells=fireball,lightning-bolt');

        // Assert
        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(1, $data, 'Should return 1 item with BOTH Fireball AND Lightning Bolt');
        $this->assertEquals('Staff of Power', $data[0]['name']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_items_by_multiple_spells_with_or_operator(): void
    {
        // Create spells
        $fireball = Spell::factory()->create([
            'name' => 'Fireball',
            'slug' => 'fireball',
            'level' => 3,
        ]);

        $lightningBolt = Spell::factory()->create([
            'name' => 'Lightning Bolt',
            'slug' => 'lightning-bolt',
            'level' => 3,
        ]);

        // Create items
        $wandOfFireballs = Item::factory()->create(['name' => 'Wand of Fireballs']);
        $wandOfFireballs->spells()->attach($fireball->id);

        $staffOfPower = Item::factory()->create(['name' => 'Staff of Power']);
        $staffOfPower->spells()->attach([$fireball->id, $lightningBolt->id]);

        $wandOfLightning = Item::factory()->create(['name' => 'Wand of Lightning Bolts']);
        $wandOfLightning->spells()->attach($lightningBolt->id);

        $potionOfHealing = Item::factory()->create(['name' => 'Potion of Healing']);
        // No spells

        // Act - OR operator
        $response = $this->getJson('/api/v1/items?spells=fireball,lightning-bolt&spells_operator=OR');

        // Assert
        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(3, $data, 'Should return 3 items with EITHER Fireball OR Lightning Bolt');

        $itemNames = collect($data)->pluck('name')->sort()->values()->all();
        $this->assertEquals([
            'Staff of Power',
            'Wand of Fireballs',
            'Wand of Lightning Bolts',
        ], $itemNames);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_items_by_spell_level(): void
    {
        // Create spells
        $fireball = Spell::factory()->create([
            'name' => 'Fireball',
            'slug' => 'fireball',
            'level' => 3,
        ]);

        $teleport = Spell::factory()->create([
            'name' => 'Teleport',
            'slug' => 'teleport',
            'level' => 7,
        ]);

        $wish = Spell::factory()->create([
            'name' => 'Wish',
            'slug' => 'wish',
            'level' => 9,
        ]);

        // Create items
        $wandOfFireballs = Item::factory()->create(['name' => 'Wand of Fireballs']);
        $wandOfFireballs->spells()->attach($fireball->id);

        $staffOfPower = Item::factory()->create(['name' => 'Staff of Power']);
        $staffOfPower->spells()->attach([$fireball->id, $teleport->id]);

        $ringOfWishes = Item::factory()->create(['name' => 'Ring of Three Wishes']);
        $ringOfWishes->spells()->attach($wish->id);

        // Act - Filter by spell level 7
        $response = $this->getJson('/api/v1/items?spell_level=7');

        // Assert
        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(1, $data, 'Should return 1 item with 7th level spells');
        $this->assertEquals('Staff of Power', $data[0]['name']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_items_by_combined_spell_and_spell_level(): void
    {
        // Create spells
        $fireball = Spell::factory()->create([
            'name' => 'Fireball',
            'slug' => 'fireball',
            'level' => 3,
        ]);

        $teleport = Spell::factory()->create([
            'name' => 'Teleport',
            'slug' => 'teleport',
            'level' => 7,
        ]);

        // Create items
        $wandOfFireballs = Item::factory()->create(['name' => 'Wand of Fireballs']);
        $wandOfFireballs->spells()->attach($fireball->id);

        $staffOfPower = Item::factory()->create(['name' => 'Staff of Power']);
        $staffOfPower->spells()->attach([$fireball->id, $teleport->id]);

        // Act - Filter by specific spell AND spell level
        $response = $this->getJson('/api/v1/items?spells=teleport&spell_level=7');

        // Assert
        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(1, $data, 'Should return 1 item with Teleport at 7th level');
        $this->assertEquals('Staff of Power', $data[0]['name']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_items_by_has_charges(): void
    {
        // Create item with charges
        $wandOfFireballs = Item::factory()->create([
            'name' => 'Wand of Fireballs',
            'charges_max' => '7',
        ]);

        // Create item without charges
        $potionOfHealing = Item::factory()->create([
            'name' => 'Potion of Healing',
            'charges_max' => null,
        ]);

        // Act
        $response = $this->getJson('/api/v1/items?has_charges=true');

        // Assert
        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(1, $data, 'Should return 1 item with charges');
        $this->assertEquals('Wand of Fireballs', $data[0]['name']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_items_by_item_type(): void
    {
        // Create spells
        $fireball = Spell::factory()->create([
            'name' => 'Fireball',
            'slug' => 'fireball',
            'level' => 3,
        ]);

        // Create item types
        $wandType = \App\Models\ItemType::firstOrCreate(['code' => 'WD'], ['name' => 'Wand']);
        $staffType = \App\Models\ItemType::firstOrCreate(['code' => 'ST'], ['name' => 'Staff']);

        // Create items
        $wandOfFireballs = Item::factory()->create([
            'name' => 'Wand of Fireballs',
            'item_type_id' => $wandType->id,
        ]);
        $wandOfFireballs->spells()->attach($fireball->id);

        $staffOfPower = Item::factory()->create([
            'name' => 'Staff of Power',
            'item_type_id' => $staffType->id,
        ]);
        $staffOfPower->spells()->attach($fireball->id);

        // Act - Filter by wand type
        $response = $this->getJson('/api/v1/items?type=WD');

        // Assert
        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(1, $data, 'Should return 1 wand');
        $this->assertEquals('Wand of Fireballs', $data[0]['name']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_combines_spell_filter_with_item_type_and_rarity(): void
    {
        // Create spells
        $fireball = Spell::factory()->create([
            'name' => 'Fireball',
            'slug' => 'fireball',
            'level' => 3,
        ]);

        // Create item types
        $wandType = \App\Models\ItemType::firstOrCreate(['code' => 'WD'], ['name' => 'Wand']);
        $staffType = \App\Models\ItemType::firstOrCreate(['code' => 'ST'], ['name' => 'Staff']);

        // Create items
        $rareWandOfFireballs = Item::factory()->create([
            'name' => 'Wand of Fireballs',
            'item_type_id' => $wandType->id,
            'rarity' => 'rare',
        ]);
        $rareWandOfFireballs->spells()->attach($fireball->id);

        $commonWandOfFireballs = Item::factory()->create([
            'name' => 'Lesser Wand of Fireballs',
            'item_type_id' => $wandType->id,
            'rarity' => 'common',
        ]);
        $commonWandOfFireballs->spells()->attach($fireball->id);

        $rareStaffOfPower = Item::factory()->create([
            'name' => 'Staff of Power',
            'item_type_id' => $staffType->id,
            'rarity' => 'rare',
        ]);
        $rareStaffOfPower->spells()->attach($fireball->id);

        // Act - Filter by spell + type + rarity
        $response = $this->getJson('/api/v1/items?spells=fireball&type=WD&rarity=rare');

        // Assert
        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(1, $data, 'Should return 1 rare wand with Fireball');
        $this->assertEquals('Wand of Fireballs', $data[0]['name']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_empty_result_when_no_items_match_spell_filter(): void
    {
        // Create spell
        $wish = Spell::factory()->create([
            'name' => 'Wish',
            'slug' => 'wish',
            'level' => 9,
        ]);

        // Create item with different spell
        $wandOfFireballs = Item::factory()->create(['name' => 'Wand of Fireballs']);
        $fireball = Spell::factory()->create(['slug' => 'fireball']);
        $wandOfFireballs->spells()->attach($fireball->id);

        // Act
        $response = $this->getJson('/api/v1/items?spells=wish');

        // Assert
        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(0, $data, 'Should return 0 items');
    }
}
