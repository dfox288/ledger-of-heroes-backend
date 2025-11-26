<?php

namespace Tests\Feature\Models;

use App\Models\AbilityScore;
use App\Models\EntitySpell;
use App\Models\Race;
use App\Models\Spell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class EntitySpellModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Only seed if ability scores don't exist
        if (AbilityScore::count() === 0) {
            $this->seed(\Database\Seeders\AbilityScoreSeeder::class);
        }
    }

    public function test_entity_spell_belongs_to_spell(): void
    {
        $spell = Spell::factory()->create();
        $race = Race::factory()->create();

        $entitySpell = EntitySpell::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'spell_id' => $spell->id,
            'is_cantrip' => true,
        ]);

        $this->assertInstanceOf(Spell::class, $entitySpell->spell);
        $this->assertEquals($spell->id, $entitySpell->spell->id);
    }

    public function test_entity_spell_belongs_to_ability_score(): void
    {
        $spell = Spell::factory()->create();
        $race = Race::factory()->create();
        $cha = AbilityScore::where('code', 'CHA')->first();

        $entitySpell = EntitySpell::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'spell_id' => $spell->id,
            'ability_score_id' => $cha->id,
            'is_cantrip' => false,
            'level_requirement' => 3,
        ]);

        $this->assertInstanceOf(AbilityScore::class, $entitySpell->abilityScore);
        $this->assertEquals('CHA', $entitySpell->abilityScore->code);
    }

    public function test_entity_spell_has_polymorphic_reference(): void
    {
        $spell = Spell::factory()->create();
        $race = Race::factory()->create();

        $entitySpell = EntitySpell::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'spell_id' => $spell->id,
            'is_cantrip' => true,
        ]);

        $this->assertInstanceOf(Race::class, $entitySpell->reference);
        $this->assertEquals($race->id, $entitySpell->reference->id);
    }
}
