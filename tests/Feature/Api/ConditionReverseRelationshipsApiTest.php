<?php

namespace Tests\Feature\Api;

use App\Models\Condition;
use App\Models\Monster;
use App\Models\Spell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConditionReverseRelationshipsApiTest extends TestCase
{
    use RefreshDatabase;

    // ========================================
    // Spells Endpoint Tests
    // ========================================

    #[Test]
    public function it_returns_spells_for_condition()
    {
        // Use seeded condition
        $poisoned = Condition::where('slug', 'poisoned')->first();

        $poisonSpray = Spell::factory()->create(['name' => 'Poison Spray', 'slug' => 'poison-spray']);
        $cloudkill = Spell::factory()->create(['name' => 'Cloudkill', 'slug' => 'cloudkill']);
        $fireball = Spell::factory()->create(['name' => 'Fireball', 'slug' => 'fireball']);

        // Attach condition to spells via entity_conditions
        $poisoned->spells()->attach($poisonSpray, [
            'effect_type' => 'inflicts',
            'description' => 'Target becomes poisoned',
        ]);

        $poisoned->spells()->attach($cloudkill, [
            'effect_type' => 'inflicts',
            'description' => 'Creatures in area become poisoned',
        ]);

        $response = $this->getJson("/api/v1/conditions/poisoned/spells");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Cloudkill')
            ->assertJsonPath('data.1.name', 'Poison Spray');
    }

    #[Test]
    public function it_returns_empty_when_condition_has_no_spells()
    {
        $custom = Condition::factory()->create(['slug' => 'custom-condition']);

        $response = $this->getJson("/api/v1/conditions/custom-condition/spells");

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_accepts_numeric_id_for_spells_endpoint()
    {
        $condition = Condition::factory()->create();
        $spell = Spell::factory()->create();
        $condition->spells()->attach($spell, ['effect_type' => 'inflicts']);

        $response = $this->getJson("/api/v1/conditions/{$condition->id}/spells");

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function it_paginates_spell_results_for_condition()
    {
        // Use seeded condition
        $stunned = Condition::where('slug', 'stunned')->first();
        $spells = Spell::factory()->count(75)->create();

        foreach ($spells as $spell) {
            $stunned->spells()->attach($spell, ['effect_type' => 'inflicts']);
        }

        $response = $this->getJson("/api/v1/conditions/stunned/spells?per_page=25");

        $response->assertOk()
            ->assertJsonCount(25, 'data')
            ->assertJsonPath('meta.total', 75)
            ->assertJsonPath('meta.per_page', 25);
    }

    // ========================================
    // Monsters Endpoint Tests
    // ========================================

    #[Test]
    public function it_returns_monsters_for_condition()
    {
        $frightened = Condition::where('slug', 'frightened')->first();

        $dragon = Monster::factory()->create(['name' => 'Adult Red Dragon', 'slug' => 'adult-red-dragon']);
        $beholder = Monster::factory()->create(['name' => 'Beholder', 'slug' => 'beholder']);
        $goblin = Monster::factory()->create(['name' => 'Goblin', 'slug' => 'goblin']);

        // Attach condition to monsters via entity_conditions
        $frightened->monsters()->attach($dragon, [
            'effect_type' => 'inflicts',
            'description' => 'Frightful Presence',
        ]);

        $frightened->monsters()->attach($beholder, [
            'effect_type' => 'inflicts',
            'description' => 'Fear Ray',
        ]);

        $response = $this->getJson("/api/v1/conditions/frightened/monsters");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Adult Red Dragon')
            ->assertJsonPath('data.1.name', 'Beholder');
    }

    #[Test]
    public function it_returns_empty_when_condition_has_no_monsters()
    {
        $custom = Condition::factory()->create(['slug' => 'custom-condition']);

        $response = $this->getJson("/api/v1/conditions/custom-condition/monsters");

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_accepts_numeric_id_for_monsters_endpoint()
    {
        $condition = Condition::factory()->create();
        $monster = Monster::factory()->create();
        $condition->monsters()->attach($monster, ['effect_type' => 'inflicts']);

        $response = $this->getJson("/api/v1/conditions/{$condition->id}/monsters");

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function it_paginates_monster_results_for_condition()
    {
        $paralyzed = Condition::where('slug', 'paralyzed')->first();
        $monsters = Monster::factory()->count(75)->create();

        foreach ($monsters as $monster) {
            $paralyzed->monsters()->attach($monster, ['effect_type' => 'inflicts']);
        }

        $response = $this->getJson("/api/v1/conditions/paralyzed/monsters?per_page=25");

        $response->assertOk()
            ->assertJsonCount(25, 'data')
            ->assertJsonPath('meta.total', 75)
            ->assertJsonPath('meta.per_page', 25);
    }
}
