<?php

namespace Tests\Feature\Api;

use App\Models\DamageType;
use App\Models\Spell;
use App\Models\SpellEffect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DamageTypeReverseRelationshipsApiTest extends TestCase
{
    use RefreshDatabase;

    // ========================================
    // Spells Endpoint Tests
    // ========================================

    #[Test]
    public function it_returns_spells_for_damage_type()
    {
        $fire = $this->getDamageType('Fire');

        $fireball = Spell::factory()->create(['name' => 'Fireball', 'slug' => 'fireball']);
        $burningHands = Spell::factory()->create(['name' => 'Burning Hands', 'slug' => 'burning-hands']);
        $iceStorm = Spell::factory()->create(['name' => 'Ice Storm', 'slug' => 'ice-storm']);

        // Link fire spells via spell_effects
        SpellEffect::factory()->create([
            'spell_id' => $fireball->id,
            'damage_type_id' => $fire->id,
        ]);

        SpellEffect::factory()->create([
            'spell_id' => $burningHands->id,
            'damage_type_id' => $fire->id,
        ]);

        // Ice storm uses different damage type - should not appear
        $cold = $this->getDamageType('Cold');
        SpellEffect::factory()->create([
            'spell_id' => $iceStorm->id,
            'damage_type_id' => $cold->id,
        ]);

        $response = $this->getJson("/api/v1/damage-types/fire/spells");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Burning Hands')
            ->assertJsonPath('data.1.name', 'Fireball');
    }

    #[Test]
    public function it_returns_empty_when_damage_type_has_no_spells()
    {
        $radiant = $this->getDamageType('Radiant');

        $response = $this->getJson("/api/v1/damage-types/radiant/spells");

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_accepts_numeric_id_for_spells_endpoint()
    {
        $fire = $this->getDamageType('Fire');
        $spell = Spell::factory()->create();
        SpellEffect::factory()->create([
            'spell_id' => $spell->id,
            'damage_type_id' => $fire->id,
        ]);

        $response = $this->getJson("/api/v1/damage-types/{$fire->id}/spells");

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function it_paginates_spell_results_for_damage_type()
    {
        $fire = $this->getDamageType('Fire');

        $spells = Spell::factory()->count(75)->create();
        foreach ($spells as $spell) {
            SpellEffect::factory()->create([
                'spell_id' => $spell->id,
                'damage_type_id' => $fire->id,
            ]);
        }

        $response = $this->getJson("/api/v1/damage-types/fire/spells?per_page=25");

        $response->assertOk()
            ->assertJsonCount(25, 'data')
            ->assertJsonPath('meta.total', 75)
            ->assertJsonPath('meta.per_page', 25);
    }
}
