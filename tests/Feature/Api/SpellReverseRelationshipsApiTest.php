<?php

namespace Tests\Feature\Api;

use App\Models\CharacterClass;
use App\Models\Item;
use App\Models\Monster;
use App\Models\Race;
use App\Models\Spell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SpellReverseRelationshipsApiTest extends TestCase
{
    use RefreshDatabase;

    // ========================================
    // Classes Endpoint Tests
    // ========================================

    #[Test]
    public function it_returns_classes_that_can_learn_spell()
    {
        $spell = Spell::factory()->create(['slug' => 'fireball', 'name' => 'Fireball']);
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'slug' => 'wizard']);
        $sorcerer = CharacterClass::factory()->create(['name' => 'Sorcerer', 'slug' => 'sorcerer']);
        $cleric = CharacterClass::factory()->create(['name' => 'Cleric', 'slug' => 'cleric']);

        // Attach spell to wizard and sorcerer only
        $wizard->spells()->attach($spell);
        $sorcerer->spells()->attach($spell);

        $response = $this->getJson("/api/v1/spells/{$spell->slug}/classes");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Sorcerer') // Ordered by name
            ->assertJsonPath('data.1.name', 'Wizard');
    }

    #[Test]
    public function it_returns_empty_when_spell_has_no_classes()
    {
        $spell = Spell::factory()->create(['slug' => 'custom-spell', 'name' => 'Custom Spell']);

        $response = $this->getJson("/api/v1/spells/{$spell->slug}/classes");

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_accepts_numeric_id_for_classes_endpoint()
    {
        $spell = Spell::factory()->create(['name' => 'Test Spell']);
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard']);
        $wizard->spells()->attach($spell);

        $response = $this->getJson("/api/v1/spells/{$spell->id}/classes");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Wizard');
    }

    // ========================================
    // Monsters Endpoint Tests
    // ========================================

    #[Test]
    public function it_returns_monsters_that_can_cast_spell()
    {
        $spell = Spell::factory()->create(['slug' => 'fireball', 'name' => 'Fireball']);
        $lich = Monster::factory()->create(['name' => 'Lich', 'slug' => 'lich']);
        $archmage = Monster::factory()->create(['name' => 'Archmage', 'slug' => 'archmage']);
        $goblin = Monster::factory()->create(['name' => 'Goblin', 'slug' => 'goblin']);

        // Attach spell to lich and archmage via entity_spells
        $lich->entitySpells()->attach($spell, ['usage_limit' => '1/day']);
        $archmage->entitySpells()->attach($spell, ['usage_limit' => 'at will']);

        $response = $this->getJson("/api/v1/spells/{$spell->slug}/monsters");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Archmage') // Ordered by name
            ->assertJsonPath('data.1.name', 'Lich');
    }

    #[Test]
    public function it_returns_empty_when_spell_has_no_monsters()
    {
        $spell = Spell::factory()->create(['slug' => 'cure-wounds', 'name' => 'Cure Wounds']);

        $response = $this->getJson("/api/v1/spells/{$spell->slug}/monsters");

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_accepts_numeric_id_for_monsters_endpoint()
    {
        $spell = Spell::factory()->create(['name' => 'Test Spell']);
        $lich = Monster::factory()->create(['name' => 'Lich']);
        $lich->entitySpells()->attach($spell);

        $response = $this->getJson("/api/v1/spells/{$spell->id}/monsters");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Lich');
    }

    // ========================================
    // Items Endpoint Tests
    // ========================================

    #[Test]
    public function it_returns_items_that_contain_spell()
    {
        $spell = Spell::factory()->create(['slug' => 'fireball', 'name' => 'Fireball']);
        $staff = Item::factory()->create(['name' => 'Staff of Fire', 'slug' => 'staff-of-fire']);
        $wand = Item::factory()->create(['name' => 'Wand of Fireballs', 'slug' => 'wand-of-fireballs']);
        $sword = Item::factory()->create(['name' => 'Longsword', 'slug' => 'longsword']);

        // Attach spell to staff and wand via entity_spells
        $staff->spells()->attach($spell, ['charges_cost_min' => 3, 'charges_cost_max' => 3]);
        $wand->spells()->attach($spell, ['charges_cost_min' => 1, 'charges_cost_max' => 1]);

        $response = $this->getJson("/api/v1/spells/{$spell->slug}/items");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Staff of Fire') // Ordered by name
            ->assertJsonPath('data.1.name', 'Wand of Fireballs');
    }

    #[Test]
    public function it_returns_empty_when_spell_has_no_items()
    {
        $spell = Spell::factory()->create(['slug' => 'wish', 'name' => 'Wish']);

        $response = $this->getJson("/api/v1/spells/{$spell->slug}/items");

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_accepts_numeric_id_for_items_endpoint()
    {
        $spell = Spell::factory()->create(['name' => 'Test Spell']);
        $staff = Item::factory()->create(['name' => 'Magic Staff']);
        $staff->spells()->attach($spell);

        $response = $this->getJson("/api/v1/spells/{$spell->id}/items");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Magic Staff');
    }

    // ========================================
    // Races Endpoint Tests
    // ========================================

    #[Test]
    public function it_returns_races_that_can_cast_spell()
    {
        $spell = Spell::factory()->create(['slug' => 'dancing-lights', 'name' => 'Dancing Lights']);
        $drow = Race::factory()->create(['name' => 'Drow', 'slug' => 'drow']);
        $highElf = Race::factory()->create(['name' => 'High Elf', 'slug' => 'high-elf']);
        $human = Race::factory()->create(['name' => 'Human', 'slug' => 'human']);

        // Create EntitySpell records for drow and high elf
        \App\Models\EntitySpell::create([
            'reference_type' => Race::class,
            'reference_id' => $drow->id,
            'spell_id' => $spell->id,
            'level_requirement' => 1,
        ]);
        \App\Models\EntitySpell::create([
            'reference_type' => Race::class,
            'reference_id' => $highElf->id,
            'spell_id' => $spell->id,
            'is_cantrip' => true,
        ]);

        $response = $this->getJson("/api/v1/spells/{$spell->slug}/races");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Drow') // Ordered by name
            ->assertJsonPath('data.1.name', 'High Elf');
    }

    #[Test]
    public function it_returns_empty_when_spell_has_no_races()
    {
        $spell = Spell::factory()->create(['slug' => 'power-word-kill', 'name' => 'Power Word Kill']);

        $response = $this->getJson("/api/v1/spells/{$spell->slug}/races");

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_accepts_numeric_id_for_races_endpoint()
    {
        $spell = Spell::factory()->create(['name' => 'Test Spell']);
        $drow = Race::factory()->create(['name' => 'Drow']);

        \App\Models\EntitySpell::create([
            'reference_type' => Race::class,
            'reference_id' => $drow->id,
            'spell_id' => $spell->id,
        ]);

        $response = $this->getJson("/api/v1/spells/{$spell->id}/races");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Drow');
    }

    // ========================================
    // Error Handling Tests
    // ========================================

    #[Test]
    public function it_returns_404_for_nonexistent_spell_classes()
    {
        $response = $this->getJson('/api/v1/spells/nonexistent-spell/classes');

        $response->assertNotFound();
    }

    #[Test]
    public function it_returns_404_for_nonexistent_spell_monsters()
    {
        $response = $this->getJson('/api/v1/spells/999999/monsters');

        $response->assertNotFound();
    }

    #[Test]
    public function it_returns_404_for_nonexistent_spell_items()
    {
        $response = $this->getJson('/api/v1/spells/invalid-slug/items');

        $response->assertNotFound();
    }

    #[Test]
    public function it_returns_404_for_nonexistent_spell_races()
    {
        $response = $this->getJson('/api/v1/spells/999999/races');

        $response->assertNotFound();
    }
}
