<?php

namespace Tests\Feature\Api;

use App\Models\CharacterClass;
use App\Models\Item;
use App\Models\Monster;
use App\Models\Race;
use App\Models\Spell;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Api\Concerns\ReverseRelationshipTestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class SpellReverseRelationshipsApiTest extends ReverseRelationshipTestCase
{
    // ========================================
    // Classes Endpoint Tests
    // ========================================

    #[Test]
    public function it_returns_classes_that_can_learn_spell()
    {
        $spell = Spell::factory()->create(['slug' => 'fireball-'.uniqid(), 'name' => 'Fireball']);
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'slug' => 'wizard-'.uniqid()]);
        $sorcerer = CharacterClass::factory()->create(['name' => 'Sorcerer', 'slug' => 'sorcerer-'.uniqid()]);
        $cleric = CharacterClass::factory()->create(['name' => 'Cleric', 'slug' => 'cleric-'.uniqid()]);

        $wizard->spells()->attach($spell);
        $sorcerer->spells()->attach($spell);

        $this->assertReturnsRelatedEntities("/api/v1/spells/{$spell->slug}/classes", 2, ['Sorcerer', 'Wizard']);
    }

    #[Test]
    public function it_returns_empty_when_spell_has_no_classes()
    {
        $spell = Spell::factory()->create(['slug' => 'custom-spell', 'name' => 'Custom Spell']);

        $this->assertReturnsEmpty("/api/v1/spells/{$spell->slug}/classes");
    }

    #[Test]
    public function it_accepts_numeric_id_for_classes_endpoint()
    {
        $spell = Spell::factory()->create(['name' => 'Test Spell']);
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard']);
        $wizard->spells()->attach($spell);

        $this->assertAcceptsAlternativeIdentifier("/api/v1/spells/{$spell->id}/classes");
    }

    // ========================================
    // Monsters Endpoint Tests
    // ========================================

    #[Test]
    public function it_returns_monsters_that_can_cast_spell()
    {
        $spell = Spell::factory()->create(['slug' => 'fireball-'.uniqid(), 'name' => 'Fireball']);
        $lich = Monster::factory()->create(['name' => 'Lich', 'slug' => 'lich-'.uniqid()]);
        $archmage = Monster::factory()->create(['name' => 'Archmage', 'slug' => 'archmage-'.uniqid()]);
        $goblin = Monster::factory()->create(['name' => 'Goblin', 'slug' => 'goblin-'.uniqid()]);

        $lich->spells()->attach($spell, ['usage_limit' => '1/day']);
        $archmage->spells()->attach($spell, ['usage_limit' => 'at will']);

        $this->assertReturnsRelatedEntities("/api/v1/spells/{$spell->slug}/monsters", 2, ['Archmage', 'Lich']);
    }

    #[Test]
    public function it_returns_empty_when_spell_has_no_monsters()
    {
        $spell = Spell::factory()->create(['slug' => 'cure-wounds', 'name' => 'Cure Wounds']);

        $this->assertReturnsEmpty("/api/v1/spells/{$spell->slug}/monsters");
    }

    #[Test]
    public function it_accepts_numeric_id_for_monsters_endpoint()
    {
        $spell = Spell::factory()->create(['name' => 'Test Spell']);
        $lich = Monster::factory()->create(['name' => 'Lich']);
        $lich->spells()->attach($spell);

        $this->assertAcceptsAlternativeIdentifier("/api/v1/spells/{$spell->id}/monsters");
    }

    // ========================================
    // Items Endpoint Tests
    // ========================================

    #[Test]
    public function it_returns_items_that_contain_spell()
    {
        $spell = Spell::factory()->create(['slug' => 'fireball-'.uniqid(), 'name' => 'Fireball']);
        $staff = Item::factory()->create(['name' => 'Staff of Fire', 'slug' => 'staff-of-fire-'.uniqid()]);
        $wand = Item::factory()->create(['name' => 'Wand of Fireballs', 'slug' => 'wand-of-fireballs-'.uniqid()]);
        $sword = Item::factory()->create(['name' => 'Longsword', 'slug' => 'longsword-'.uniqid()]);

        $staff->spells()->attach($spell, ['charges_cost_min' => 3, 'charges_cost_max' => 3]);
        $wand->spells()->attach($spell, ['charges_cost_min' => 1, 'charges_cost_max' => 1]);

        $this->assertReturnsRelatedEntities("/api/v1/spells/{$spell->slug}/items", 2, ['Staff of Fire', 'Wand of Fireballs']);
    }

    #[Test]
    public function it_returns_empty_when_spell_has_no_items()
    {
        $spell = Spell::factory()->create(['slug' => 'wish', 'name' => 'Wish']);

        $this->assertReturnsEmpty("/api/v1/spells/{$spell->slug}/items");
    }

    #[Test]
    public function it_accepts_numeric_id_for_items_endpoint()
    {
        $spell = Spell::factory()->create(['name' => 'Test Spell']);
        $staff = Item::factory()->create(['name' => 'Magic Staff']);
        $staff->spells()->attach($spell);

        $this->assertAcceptsAlternativeIdentifier("/api/v1/spells/{$spell->id}/items");
    }

    // ========================================
    // Races Endpoint Tests
    // ========================================

    #[Test]
    public function it_returns_races_that_can_cast_spell()
    {
        $spell = Spell::factory()->create(['slug' => 'dancing-lights-'.uniqid(), 'name' => 'Dancing Lights']);
        $drow = Race::factory()->create(['name' => 'Drow', 'slug' => 'drow-'.uniqid()]);
        $highElf = Race::factory()->create(['name' => 'High Elf', 'slug' => 'high-elf-'.uniqid()]);
        $human = Race::factory()->create(['name' => 'Human', 'slug' => 'human-'.uniqid()]);

        \App\Models\EntitySpell::create(['reference_type' => Race::class, 'reference_id' => $drow->id, 'spell_id' => $spell->id, 'level_requirement' => 1]);
        \App\Models\EntitySpell::create(['reference_type' => Race::class, 'reference_id' => $highElf->id, 'spell_id' => $spell->id, 'is_cantrip' => true]);

        $this->assertReturnsRelatedEntities("/api/v1/spells/{$spell->slug}/races", 2, ['Drow', 'High Elf']);
    }

    #[Test]
    public function it_returns_empty_when_spell_has_no_races()
    {
        $spell = Spell::factory()->create(['slug' => 'power-word-kill', 'name' => 'Power Word Kill']);

        $this->assertReturnsEmpty("/api/v1/spells/{$spell->slug}/races");
    }

    #[Test]
    public function it_accepts_numeric_id_for_races_endpoint()
    {
        $spell = Spell::factory()->create(['name' => 'Test Spell']);
        $drow = Race::factory()->create(['name' => 'Drow']);

        \App\Models\EntitySpell::create(['reference_type' => Race::class, 'reference_id' => $drow->id, 'spell_id' => $spell->id]);

        $this->assertAcceptsAlternativeIdentifier("/api/v1/spells/{$spell->id}/races");
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
