<?php

namespace Tests\Feature\Api;

use App\Models\Spell;
use App\Models\SpellSchool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SpellSchoolReverseRelationshipsApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_spells_for_spell_school()
    {
        $evocation = SpellSchool::where('code', 'EV')->first();

        $fireball = Spell::factory()->create([
            'spell_school_id' => $evocation->id,
            'name' => 'Fireball',
            'slug' => 'fireball',
        ]);

        $magicMissile = Spell::factory()->create([
            'spell_school_id' => $evocation->id,
            'name' => 'Magic Missile',
            'slug' => 'magic-missile',
        ]);

        // Different school - should not appear
        $abjuration = SpellSchool::where('code', 'A')->first();
        Spell::factory()->create([
            'spell_school_id' => $abjuration->id,
            'name' => 'Shield',
        ]);

        $response = $this->getJson("/api/v1/spell-schools/{$evocation->id}/spells");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Fireball')
            ->assertJsonPath('data.1.name', 'Magic Missile');
    }
}
