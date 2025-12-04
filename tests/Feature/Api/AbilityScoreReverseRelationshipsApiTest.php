<?php

namespace Tests\Feature\Api;

use App\Models\AbilityScore;
use App\Models\Spell;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Api\Concerns\ReverseRelationshipTestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class AbilityScoreReverseRelationshipsApiTest extends ReverseRelationshipTestCase
{
    protected $seeder = \Database\Seeders\TestDatabaseSeeder::class;

    #[Test]
    public function it_returns_spells_for_ability_score()
    {
        $dexterity = AbilityScore::where('code', 'DEX')->first();

        $fireball = Spell::factory()->create(['name' => 'Fireball', 'slug' => 'fireball']);
        $lightningBolt = Spell::factory()->create(['name' => 'Lightning Bolt', 'slug' => 'lightning-bolt']);

        $dexterity->entitiesRequiringSave()->attach($fireball, ['save_effect' => 'half_damage', 'is_initial_save' => true]);
        $dexterity->entitiesRequiringSave()->attach($lightningBolt, ['save_effect' => 'half_damage', 'is_initial_save' => true]);

        // Different ability score - should not appear
        $wisdom = AbilityScore::where('code', 'WIS')->first();
        $holdPerson = Spell::factory()->create(['name' => 'Hold Person']);
        $wisdom->entitiesRequiringSave()->attach($holdPerson, ['save_effect' => 'negates', 'is_initial_save' => true]);

        $this->assertReturnsRelatedEntities(
            "/api/v1/lookups/ability-scores/{$dexterity->id}/spells",
            2,
            ['Fireball', 'Lightning Bolt']
        );
    }

    #[Test]
    public function it_returns_empty_when_ability_score_has_no_spells()
    {
        $charisma = AbilityScore::where('code', 'CHA')->first();

        $this->assertReturnsEmpty("/api/v1/lookups/ability-scores/{$charisma->id}/spells");
    }

    #[Test]
    public function it_accepts_code_for_spells_endpoint()
    {
        $dex = AbilityScore::where('code', 'DEX')->first();
        $spell = Spell::factory()->create();
        $dex->entitiesRequiringSave()->attach($spell, ['save_effect' => 'half_damage', 'is_initial_save' => true]);

        $this->assertAcceptsAlternativeIdentifier('/api/v1/lookups/ability-scores/DEX/spells');
    }

    #[Test]
    public function it_paginates_spell_results()
    {
        $con = AbilityScore::where('code', 'CON')->first();

        $this->createMultipleEntities(75, function () use ($con) {
            $spell = Spell::factory()->create();
            $con->entitiesRequiringSave()->attach($spell, ['save_effect' => 'half_damage', 'is_initial_save' => true]);

            return $spell;
        });

        $this->assertPaginatesCorrectly(
            "/api/v1/lookups/ability-scores/{$con->id}/spells?per_page=25",
            25,
            75,
            25
        );
    }
}
