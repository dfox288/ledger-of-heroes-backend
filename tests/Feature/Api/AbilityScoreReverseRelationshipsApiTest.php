<?php

namespace Tests\Feature\Api;

use App\Models\AbilityScore;
use App\Models\Spell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class AbilityScoreReverseRelationshipsApiTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = \Database\Seeders\TestDatabaseSeeder::class;

    #[Test]
    public function it_returns_spells_for_ability_score()
    {
        $dexterity = AbilityScore::where('code', 'DEX')->first();

        $fireball = Spell::factory()->create([
            'name' => 'Fireball',
            'slug' => 'fireball',
        ]);

        $lightningBolt = Spell::factory()->create([
            'name' => 'Lightning Bolt',
            'slug' => 'lightning-bolt',
        ]);

        // Attach spells to ability score via entity_saving_throws
        $dexterity->entitiesRequiringSave()->attach($fireball, [
            'save_effect' => 'half_damage',
            'is_initial_save' => true,
        ]);

        $dexterity->entitiesRequiringSave()->attach($lightningBolt, [
            'save_effect' => 'half_damage',
            'is_initial_save' => true,
        ]);

        // Different ability score - should not appear
        $wisdom = AbilityScore::where('code', 'WIS')->first();
        $holdPerson = Spell::factory()->create(['name' => 'Hold Person']);
        $wisdom->entitiesRequiringSave()->attach($holdPerson, [
            'save_effect' => 'negates',
            'is_initial_save' => true,
        ]);

        $response = $this->getJson("/api/v1/lookups/ability-scores/{$dexterity->id}/spells");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Fireball')
            ->assertJsonPath('data.1.name', 'Lightning Bolt');
    }

    #[Test]
    public function it_returns_empty_when_ability_score_has_no_spells()
    {
        $charisma = AbilityScore::where('code', 'CHA')->first();

        $response = $this->getJson("/api/v1/lookups/ability-scores/{$charisma->id}/spells");

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_accepts_code_for_spells_endpoint()
    {
        $dex = AbilityScore::where('code', 'DEX')->first();
        $spell = Spell::factory()->create();

        $dex->entitiesRequiringSave()->attach($spell, [
            'save_effect' => 'half_damage',
            'is_initial_save' => true,
        ]);

        $response = $this->getJson('/api/v1/lookups/ability-scores/DEX/spells');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function it_paginates_spell_results()
    {
        $con = AbilityScore::where('code', 'CON')->first();

        // Create 75 spells with CON saves
        Spell::factory()->count(75)->create()->each(function ($spell) use ($con) {
            $con->entitiesRequiringSave()->attach($spell, [
                'save_effect' => 'half_damage',
                'is_initial_save' => true,
            ]);
        });

        $response = $this->getJson("/api/v1/lookups/ability-scores/{$con->id}/spells?per_page=25");

        $response->assertOk()
            ->assertJsonCount(25, 'data')
            ->assertJsonPath('meta.total', 75)
            ->assertJsonPath('meta.per_page', 25);
    }
}
