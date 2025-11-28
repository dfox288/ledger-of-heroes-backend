<?php

namespace Tests\Feature\Api;

use App\Models\Spell;
use App\Models\SpellSchool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class SpellSchoolReverseRelationshipsApiTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = \Database\Seeders\LookupSeeder::class;

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

        $response = $this->getJson("/api/v1/lookups/spell-schools/{$evocation->id}/spells");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Fireball')
            ->assertJsonPath('data.1.name', 'Magic Missile');
    }

    #[Test]
    public function it_returns_empty_when_school_has_no_spells()
    {
        $school = SpellSchool::where('code', 'D')->first();

        $response = $this->getJson("/api/v1/lookups/spell-schools/{$school->id}/spells");

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_accepts_numeric_id_for_spells_endpoint()
    {
        $school = SpellSchool::where('code', 'EV')->first();
        $spell = Spell::factory()->create(['spell_school_id' => $school->id]);

        $response = $this->getJson("/api/v1/lookups/spell-schools/{$school->id}/spells");

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function it_paginates_spell_results()
    {
        $school = SpellSchool::where('code', 'EV')->first();
        Spell::factory()->count(75)->create(['spell_school_id' => $school->id]);

        $response = $this->getJson("/api/v1/lookups/spell-schools/{$school->id}/spells?per_page=25");

        $response->assertOk()
            ->assertJsonCount(25, 'data')
            ->assertJsonPath('meta.total', 75)
            ->assertJsonPath('meta.per_page', 25);
    }
}
